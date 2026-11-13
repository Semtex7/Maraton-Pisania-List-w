<?php

define( 'MARATON_META_PIXEL_ID', '803088636462790' );
define( 'MARATON_META_PIXEL_HOST', 'maraton.amnesty.org.pl' );

add_action( 'wp_head', 'maraton_meta_pixel', 20 );

// Rendered as text/plain: Complianz turns it into a running script only after marketing consent.
// The <noscript> pixel from Meta's snippet is left out on purpose, it would track without consent.
function maraton_meta_pixel() {
    // Staging and local copies would send test traffic to the client's ad account
    if ( MARATON_META_PIXEL_HOST !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
        return;
    }

    if ( current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
<script type="text/plain" data-category="marketing" data-service="facebook">
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?php echo esc_js( MARATON_META_PIXEL_ID ); ?>');
fbq('track', 'PageView');
</script>
    <?php
}
