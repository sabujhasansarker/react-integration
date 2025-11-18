<?php

/**
 * Plugin Name: React Admin Demo
 * Version: 1.0
 * Author: You!
 * Description: React CRUD with WordPress Database
 */

// Create custom table on plugin activation
register_activation_hook(__FILE__, 'react_create_users_table');
function react_create_users_table()
{
     global $wpdb;
     $table_name = $wpdb->prefix . 'react_users';
     $charset_collate = $wpdb->get_charset_collate();

     $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        role varchar(50) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

     require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
     dbDelta($sql);

     // Verify table was created
     if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
          error_log('Failed to create table: ' . $table_name);
     }
}

// Check and create table if it doesn't exist (safety net)
add_action('admin_init', 'react_check_table_exists');
function react_check_table_exists()
{
     global $wpdb;
     $table_name = $wpdb->prefix . 'react_users';

     if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
          react_create_users_table();
     }
}

// Add admin menu
add_action('admin_menu', function () {
     add_menu_page(
          'React App',
          'React CRUD',
          'manage_options',
          'react-admin',
          'react_admin_render',
          'dashicons-admin-site-alt3',
          20
     );
});

// Enqueue scripts
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
                                             'apiUrl' => rest_url('react-crud/v1'),
                                             'nonce' => wp_create_nonce('wp_rest'),
                                        ]); ?>;
          </script>
<?php
     });
}

function react_admin_render()
{
     echo '<div id="root"></div>';
}

// Register REST API endpoints
add_action('rest_api_init', function () {
     // Get all users
     register_rest_route('react-crud/v1', '/users', [
          'methods' => 'GET',
          'callback' => 'react_get_all_users',
          'permission_callback' => function () {
               return current_user_can('manage_options');
          }
     ]);

     // Create user
     register_rest_route('react-crud/v1', '/users', [
          'methods' => 'POST',
          'callback' => 'react_create_user',
          'permission_callback' => function () {
               return current_user_can('manage_options');
          }
     ]);

     // Update user
     register_rest_route('react-crud/v1', '/users/(?P<id>\d+)', [
          'methods' => 'PUT',
          'callback' => 'react_update_user',
          'permission_callback' => function () {
               return current_user_can('manage_options');
          }
     ]);

     // Delete user
     register_rest_route('react-crud/v1', '/users/(?P<id>\d+)', [
          'methods' => 'DELETE',
          'callback' => 'react_delete_user',
          'permission_callback' => function () {
               return current_user_can('manage_options');
          }
     ]);
});

function react_get_all_users()
{
     global $wpdb;
     $table_name = $wpdb->prefix . 'react_users';

     // Check if table exists
     if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
          return new WP_Error('no_table', 'Table does not exist', ['status' => 500]);
     }

     $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
     return rest_ensure_response($results);
}

function react_create_user($request)
{
     global $wpdb;
     $table_name = $wpdb->prefix . 'react_users';

     // Check if table exists
     if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
          return new WP_Error('no_table', 'Table does not exist', ['status' => 500]);
     }

     $data = json_decode($request->get_body(), true);

     // Validate input
     if (empty($data['name']) || empty($data['email']) || empty($data['role'])) {
          return new WP_Error('invalid_data', 'All fields are required', ['status' => 400]);
     }

     $inserted = $wpdb->insert(
          $table_name,
          [
               'name' => sanitize_text_field($data['name']),
               'email' => sanitize_email($data['email']),
               'role' => sanitize_text_field($data['role'])
          ],
          ['%s', '%s', '%s']
     );

     if ($inserted) {
          return rest_ensure_response([
               'success' => true,
               'id' => $wpdb->insert_id,
               'message' => 'User created successfully'
          ]);
     }

     return new WP_Error('error', 'Failed to create user: ' . $wpdb->last_error, ['status' => 500]);
}

function react_update_user($request)
{
     global $wpdb;
     $table_name = $wpdb->prefix . 'react_users';

     // Check if table exists
     if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
          return new WP_Error('no_table', 'Table does not exist', ['status' => 500]);
     }

     $id = $request['id'];
     $data = json_decode($request->get_body(), true);

     // Validate input
     if (empty($data['name']) || empty($data['email']) || empty($data['role'])) {
          return new WP_Error('invalid_data', 'All fields are required', ['status' => 400]);
     }

     $updated = $wpdb->update(
          $table_name,
          [
               'name' => sanitize_text_field($data['name']),
               'email' => sanitize_email($data['email']),
               'role' => sanitize_text_field($data['role'])
          ],
          ['id' => $id],
          ['%s', '%s', '%s'],
          ['%d']
     );

     if ($updated !== false) {
          return rest_ensure_response([
               'success' => true,
               'message' => 'User updated successfully'
          ]);
     }

     return new WP_Error('error', 'Failed to update user: ' . $wpdb->last_error, ['status' => 500]);
}

function react_delete_user($request)
{
     global $wpdb;
     $table_name = $wpdb->prefix . 'react_users';

     // Check if table exists
     if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
          return new WP_Error('no_table', 'Table does not exist', ['status' => 500]);
     }

     $id = $request['id'];

     $deleted = $wpdb->delete($table_name, ['id' => $id], ['%d']);

     if ($deleted) {
          return rest_ensure_response([
               'success' => true,
               'message' => 'User deleted successfully'
          ]);
     }

     return new WP_Error('error', 'Failed to delete user: ' . $wpdb->last_error, ['status' => 500]);
}
