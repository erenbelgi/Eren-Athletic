<?php
/** Additive membership migrations. Never calls the plugin activation routine. */
if ( ! defined( 'ABSPATH' ) ) exit;
define( 'EA_MEMBERSHIP_SCHEMA_VERSION', '1.0.0' );

function ea_member_table( $name ) {
    return $GLOBALS['wpdb']->prefix . 'ea_' . $name;
}

function ea_membership_migrate() {
    global $wpdb;
    if ( get_option( 'ea_membership_schema_version' ) === EA_MEMBERSHIP_SCHEMA_VERSION ) return true;
    $lock = 'ea_schema_' . md5( DB_NAME . $wpdb->prefix );
    if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,10)', $lock ) ) ) return new WP_Error( 'migration', 'Üyelik kurulumu kilitlenemedi.' );
    try {
        $charset = $wpdb->get_charset_collate();
        $definitions = array(
            'user_memberships' => "id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, offer_type varchar(32) NOT NULL, offer_id bigint unsigned NOT NULL, application_id bigint unsigned NULL, status varchar(16) NOT NULL, starts_at datetime NOT NULL, ends_at datetime NOT NULL, approved_by bigint unsigned NOT NULL, approved_at datetime NOT NULL, snapshot longtext NOT NULL, activation_key char(64) NOT NULL, PRIMARY KEY (id), UNIQUE KEY activation_key (activation_key), UNIQUE KEY application_id (application_id), KEY user_status (user_id,status,ends_at), KEY offer (offer_type,offer_id)",
            'membership_entitlements' => "id bigint unsigned NOT NULL AUTO_INCREMENT, membership_id bigint unsigned NOT NULL, resource_type varchar(32) NOT NULL, resource_id bigint unsigned NOT NULL, total_units int unsigned NOT NULL, reserved_units int unsigned NOT NULL DEFAULT 0, consumed_units int unsigned NOT NULL DEFAULT 0, PRIMARY KEY (id), UNIQUE KEY membership_resource (membership_id,resource_id), KEY resource (resource_type,resource_id)",
            'booking_allocations' => "id bigint unsigned NOT NULL AUTO_INCREMENT, booking_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, membership_id bigint unsigned NOT NULL, entitlement_id bigint unsigned NOT NULL, units int unsigned NOT NULL, state varchar(24) NOT NULL, booking_start datetime NOT NULL, request_key char(64) NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY booking_id (booking_id), UNIQUE KEY request_key (request_key), KEY membership_id (membership_id), KEY entitlement_id (entitlement_id), KEY user_id (user_id)",
            'entitlement_events' => "id bigint unsigned NOT NULL AUTO_INCREMENT, membership_id bigint unsigned NOT NULL, entitlement_id bigint unsigned NOT NULL, allocation_id bigint unsigned NULL, event_type varchar(32) NOT NULL, total_delta int NOT NULL DEFAULT 0, reserved_delta int NOT NULL DEFAULT 0, consumed_delta int NOT NULL DEFAULT 0, actor_id bigint unsigned NOT NULL, created_at datetime NOT NULL, reason text NOT NULL, operation_key char(64) NOT NULL, PRIMARY KEY (id), UNIQUE KEY operation_key (operation_key), KEY entitlement_history (entitlement_id,id), KEY membership_history (membership_id,id), KEY allocation_id (allocation_id)"
        );
        foreach ( $definitions as $name => $definition ) {
            $table = ea_member_table( $name );
            if ( 'user_memberships' !== $name ) $definition .= ', FOREIGN KEY (membership_id) REFERENCES `' . ea_member_table('user_memberships') . '` (id) ON DELETE RESTRICT';
            if ( in_array($name,array('booking_allocations','entitlement_events'),true) ) $definition .= ', FOREIGN KEY (entitlement_id) REFERENCES `' . ea_member_table('membership_entitlements') . '` (id) ON DELETE RESTRICT';
            if ( 'entitlement_events' === $name ) $definition .= ', FOREIGN KEY (allocation_id) REFERENCES `' . ea_member_table('booking_allocations') . '` (id) ON DELETE RESTRICT';
            if ( 'membership_entitlements' === $name ) $definition .= ', CHECK (total_units >= reserved_units + consumed_units)';
            if ( false === $wpdb->query( "CREATE TABLE IF NOT EXISTS `$table` ($definition) ENGINE=InnoDB $charset" ) ) throw new RuntimeException( 'Üyelik tablosu oluşturulamadı: ' . $name );
            $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
            if ( 'InnoDB' !== $engine ) throw new RuntimeException( 'Üyelik tablosu InnoDB olmalı: ' . $name );
        }
        update_option( 'ea_membership_schema_version', EA_MEMBERSHIP_SCHEMA_VERSION, false );
        return true;
    } catch ( Throwable $e ) {
        return new WP_Error( 'migration', $e->getMessage() );
    } finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
}

add_action( 'admin_init', function () {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $result = ea_membership_migrate();
    if ( is_wp_error( $result ) ) add_action( 'admin_notices', function () use ( $result ) { echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>'; } );
} );

/** Explicit one-time data migration; only program duration metadata may change. */
function ea_membership_migrate_durations() {
    $result = ea_member_transaction( function () {
        global $wpdb; ea_member_admin();
        if ( get_option( 'ea_membership_duration_version' ) === '1' ) return array();
        $packages = get_posts(array('post_type'=>'ea_package','post_status'=>'publish','numberposts'=>-1));
        $map=array();
        foreach($packages as $package){
            $program=(int)get_post_meta($package->ID,'_ea_program',true);$minutes=(int)get_post_meta($package->ID,'_ea_duration',true);
            ea_member_require('ea_program'===get_post_type($program) && in_array($minutes,array(50,60,75),true),'Paket süresi veya eğitim bağlantısı geçersiz; süre geçişi durduruldu.');
            ea_member_require(!isset($map[$program])||$map[$program]===$minutes,'Aynı eğitimde farklı paket süreleri var.');$map[$program]=$minutes;
        }
        foreach($map as $program=>$minutes){
            ea_member_sql($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$program));
            $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_ea_duration'",$program));
            ea_member_require(1===$count,'Eğitim süre alanı eksik veya mükerrer.');
            ea_member_sql($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value=%s WHERE post_id=%d AND meta_key='_ea_duration'",(string)$minutes,$program));
        }
        // Write the marker inside the transaction without invoking option hooks.
        ea_member_sql($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,'1','off') ON DUPLICATE KEY UPDATE option_value='1'",'ea_membership_duration_version'));
        return $map;
    });
    if ( ! is_wp_error( $result ) ) {
        foreach ( array_keys( $result ) as $program ) clean_post_cache( $program );
        wp_cache_delete( 'ea_membership_duration_version', 'options' );
        wp_cache_delete( 'notoptions', 'options' );
    }
    return $result;
}
