<?php if(!defined('ABSPATH'))exit; ?>
<dialog id="ea-site-search" class="ea-site-search" aria-labelledby="ea-search-title">
<h2 id="ea-search-title">Site içinde ara</h2>
<form role="search" aria-label="Site içi arama" method="get" action="<?php echo esc_url(home_url('/')); ?>">
<label for="ea-site-search-input">Aranacak kelime</label>
<input type="search" id="ea-site-search-input" name="s" value="<?php echo esc_attr(get_search_query()); ?>" required>
<button type="submit">Ara</button><button type="button" data-close-search>Aramayı Kapat</button>
</form></dialog>
<noscript><p><a href="<?php echo esc_url(home_url('/?s=')); ?>">Site içi arama sayfası</a></p></noscript>
