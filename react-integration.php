<?php

/**
 * Plugin Name: React Admin Demo
 * Version: 1.0
 * Author: You!
 * Description: How to integrate react inside wp admin
 */

add_action('admin_menu', function () {
     add_menu_page(
          'React App',
          'React',
          'manage_options',
          'react-admin',
          'react_admin_render',
          'dashicons-admin-site-alt3',
          20
     );
});

add_action('admin_enqueue_scripts', 'react_admin_script');
function react_admin_script($hook)
{
     if ($hook !== 'toplevel_page_react-admin') {
          return;
     }

     wp_enqueue_script(
          'react-admin-js',
          'http://localhost:3000/src/main.jsx',
          [],
          null,
          false
     );

     add_filter('script_loader_tag', function ($tag, $handle) {
          if ($handle === 'react-admin-js') {
               $tag = str_replace('<script', '<script type="module" crossorigin', $tag);
          }
          return $tag;
     }, 10, 2);

     add_action('admin_head', function () {
?>
          <script>
               window.wpReactData = <?php echo json_encode([
                                             'siteName' => get_bloginfo('name'),
                                             'siteUrl' => get_site_url(),
                                             'apiUrl' => rest_url(),
                                             'nonce' => wp_create_nonce('wp_rest'),
                                             'currentUser' => [
                                                  'name' => wp_get_current_user()->display_name,
                                                  'email' => wp_get_current_user()->user_email,
                                             ],
                                             'customData' => [
                                                  'message' => 'Hello from WordPress!',
                                             ]
                                        ]); ?>;
          </script>
<?php
     });
}

function react_admin_render()
{
     echo '<div id="root"></div>';
}
