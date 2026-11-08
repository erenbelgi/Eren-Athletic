<?php
if(!defined('ABSPATH'))exit;
/** Presentation only: never rewrites the booking schedule or attachment provenance. */
function ea_rental_hours_data($id){
 $days=array_fill(0,7,array());$aliases=array('mon'=>0,'pzt'=>0,'tue'=>1,'sal'=>1,'wed'=>2,'car'=>2,'çar'=>2,'thu'=>3,'per'=>3,'fri'=>4,'cum'=>4,'sat'=>5,'cmt'=>5,'sun'=>6,'paz'=>6);$valid=true;
 foreach(preg_split('/\R/u',(string)get_post_meta($id,'_ea_hours',true))as$line){$line=trim($line);if($line==='')continue;
  if(!preg_match('/^([a-zç]{3})\s+((?:[01]\d|2[0-3]):[0-5]\d)\s*-\s*((?:[01]\d|2[0-3]):[0-5]\d)$/iu',$line,$m)){$valid=false;continue;}
  $day=function_exists('mb_strtolower')?mb_strtolower($m[1],'UTF-8'):strtolower($m[1]);if(!isset($aliases[$day])||$m[2]>=$m[3]){$valid=false;continue;}$days[$aliases[$day]][]=$m[2].' – '.$m[3];
 }
 foreach($days as&$ranges){$ranges=array_values(array_unique($ranges));sort($ranges,SORT_STRING);}unset($ranges);
 $uniform=$valid&&!empty($days[0]);foreach($days as$ranges)$uniform=$uniform&&$ranges===$days[0];return array('days'=>$days,'uniform'=>$uniform,'valid'=>$valid);
}
function ea_rental_hours_summary($id){$data=ea_rental_hours_data($id);return $data['uniform']?'Her gün '.str_replace(':','.',implode(' / ',$data['days'][0])):'Günlere göre çalışma saatleri';}
function ea_rental_hours_card($id){
 $data=ea_rental_hours_data($id);$html='<article class="ea-rental-hours-card"><p class="ea-rental-eyebrow">SAHANI PLANLA</p><h2>Çalışma Saatleri</h2>';
 if($data['uniform'])$html.='<p class="ea-rental-hours-main">'.esc_html(ea_rental_hours_summary($id)).'</p><p class="ea-rental-hours-days">Pazartesi – Pazar</p>';
 elseif(!array_filter($data['days']))$html.='<p>Çalışma saatleri henüz tanımlanmadı.</p>';
 else{$labels=array('Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi','Pazar');$html.='<dl class="ea-rental-hours-list">';foreach($data['days']as$i=>$ranges)$html.='<div><dt>'.esc_html($labels[$i]).'</dt><dd>'.esc_html($ranges?str_replace(':','.',implode(' / ',$ranges)):'Kapalı').'</dd></div>';$html.='</dl>';if(!$data['valid'])$html.='<p>Saat bilgilerini tesisle teyit edin.</p>';}
 $duration=absint(get_post_meta($id,'_ea_duration',true));$step=absint(get_post_meta($id,'_ea_slot_step',true));
 return $html.'<ul class="ea-rental-hours-extra"><li>Kiralama süresi: '.esc_html($duration?$duration.' dakika':'Henüz tanımlanmadı').'</li><li>Rezervasyon başlangıçları: '.esc_html($step?$step.' dakika aralıklarla':'Henüz tanımlanmadı').'</li></ul></article>';
}
function ea_rental_gallery($id){
 $ids=preg_split('/[\s,]+/',(string)get_post_meta($id,'_ea_gallery',true));$ids=array_values(array_unique(array_filter(array_merge(array(get_post_thumbnail_id($id)),array_map('absint',$ids)))));
 $html='<section class="ea-rental-section ea-rental-gallery-section" aria-labelledby="ea-rental-gallery-title"><h2 id="ea-rental-gallery-title">Saha Galerisi</h2><div class="ea-rental-gallery">';$count=0;
 foreach($ids as$photo){$file=get_attached_file($photo);$url=wp_get_attachment_image_url($photo,'full');if(!wp_attachment_is_image($photo)||!$file||!is_file($file)||!$url)continue;$alt=get_post_meta($photo,'_wp_attachment_image_alt',true)?:get_the_title($id).' — saha görünümü';
  $size=getimagesize($file);$width=$size?(int)$size[0]:380;
  $html.='<a class="ea-rental-gallery-item" data-legacy="'.(in_array((int)$photo,array(36,37,38),true)?'1':'0').'" style="max-width:'.esc_attr($width).'px" href="'.esc_url($url).'" aria-label="'.esc_attr(get_the_title($id).' — fotoğrafı büyüt, '.(++$count)).'">'.wp_get_attachment_image($photo,'large',false,array('alt'=>$alt,'loading'=>'lazy','sizes'=>'(max-width: 650px) 45vw, (max-width: 1000px) 45vw, 380px')).'</a>';
 }
 if(!$count)$html.='<p>Fotoğraf henüz eklenmedi.</p>';
 $html.='</div><dialog class="ea-rental-lightbox" aria-label="Saha fotoğrafı büyük görünüm"><button type="button" class="ea-rental-lightbox-close" aria-label="Büyük görüntüyü kapat">Kapat ×</button><button type="button" class="ea-rental-lightbox-prev" aria-label="Önceki fotoğraf">←</button><img alt=""><button type="button" class="ea-rental-lightbox-next" aria-label="Sonraki fotoğraf">→</button><p class="ea-rental-lightbox-count" role="status" aria-live="polite"></p></dialog></section>';return $html;
}
function ea_rental_cover($id){
 $ids=preg_split('/[\s,]+/',(string)get_post_meta($id,'_ea_gallery',true));foreach(array_map('absint',$ids)as$photo){$file=get_attached_file($photo);if(!get_post_meta($photo,'_ea_gallery_source_hash',true)||!$file||!is_file($file)||!wp_attachment_is_image($photo))continue;
  return wp_get_attachment_image($photo,'large',false,array('alt'=>get_post_meta($photo,'_wp_attachment_image_alt',true)?:get_the_title($id).' — saha görünümü','class'=>'ea-coach-photo','sizes'=>'(max-width: 650px) 90vw, 600px'));
 }return ea_coach_photo($id);
}
