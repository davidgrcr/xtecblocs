<?php
/**
 * Users List Table class.
 *
 * @since 3.1.0
 * @access private
 *
 * @package WordPress
 * @subpackage List_Table
 */
class WP_Users_List_Table extends WP_List_Table {

	/**
	 * Site ID to generate the Users list table for.
	 *
	 * @since 3.1.0
	 * @access public
	 * @var int
	 */
	public $site_id;

	/**
	 * Whether or not the current Users list table is for Multisite.
	 *
	 * @since 3.1.0
	 * @access public
	 * @var bool
	 */
	public $is_site_users;

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( array(
			'singular' => 'user',
			'plural'   => 'users',
			'screen'   => isset( $args['screen'] ) ? $args['screen'] : null,
		) );

		$this->is_site_users = 'site-users-network' == $this->screen->id;

		if ( $this->is_site_users )
			$this->site_id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;
	}

	/**
	 * Check the current user's permissions.
	 *
 	 * @since 3.1.0
	 * @access public
	 */
	public function ajax_user_can() {
		if ( $this->is_site_users )
			return current_user_can( 'manage_sites' );
		else
			return current_user_can( 'list_users' );
	}

	/**
	 * Prepare the users list for display.
	 *
	 * @since 3.1.0
	 * @access public
	 */
	public function prepare_items() {
		global $role, $usersearch;

		$usersearch = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';

		$role = isset( $_REQUEST['role'] ) ? $_REQUEST['role'] : '';

		$per_page = ( $this->is_site_users ) ? 'site_users_network_per_page' : 'users_per_page';
		$users_per_page = $this->get_items_per_page( $per_page );

		$paged = $this->get_pagenum();

		$args = array(
			'number' => $users_per_page,
			'offset' => ( $paged-1 ) * $users_per_page,
			'role' => $role,
			'search' => $usersearch,
			'fields' => 'all_with_meta'
		);

		if ( '' !== $args['search'] )
			$args['search'] = '*' . $args['search'] . '*';

		if ( $this->is_site_users )
			$args['blog_id'] = $this->site_id;

		if ( isset( $_REQUEST['orderby'] ) )
			$args['orderby'] = $_REQUEST['orderby'];

		if ( isset( $_REQUEST['order'] ) )
			$args['order'] = $_REQUEST['order'];

		// Query the user IDs for this page
		$wp_user_search = new WP_User_Query( $args );

		$this->items = $wp_user_search->get_results();

		$this->set_pagination_args( array(
			'total_items' => $wp_user_search->get_total(),
			'per_page' => $users_per_page,
		) );
	}

	/**
	 * Output 'no users' message.
	 *
	 * @since 3.1.0
	 * @access public
	 */
	public function no_items() {
		_e( 'No matching users were found.' );
	}

	/**
	 * Return an associative array listing all the views that can be used
	 * with this table.
	 *
	 * Provides a list of roles and user count for that role for easy
	 * filtering of the user table.
	 *
	 * @since  3.1.0
	 * @access protected
	 *
	 * @return array An array of HTML links, one for each view.
	 */
	protected function get_views() {
		global $wp_roles, $role;

		if ( $this->is_site_users ) {
			$url = 'site-users.php?id=' . $this->site_id;
			switch_to_blog( $this->site_id );
			$users_of_blog = count_users();
			restore_current_blog();
		} else {
			$url = 'users.php';
			$users_of_blog = count_users();
		}
		$total_users = $users_of_blog['total_users'];
		$avail_roles =& $users_of_blog['avail_roles'];
		unset($users_of_blog);

		$class = empty($role) ? ' class="current"' : '';
		$role_links = array();
		$role_links['all'] = "<a href='$url'$class>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_users, 'users' ), number_format_i18n( $total_users ) ) . '</a>';
		foreach ( $wp_roles->get_names() as $this_role => $name ) {
			if ( !isset($avail_roles[$this_role]) )
				continue;

			$class = '';

			if ( $this_role == $role ) {
				$class = ' class="current"';
			}

			$name = translate_user_role( $name );
			/* translators: User role name with count */
			$name = sprintf( __('%1$s <span class="count">(%2$s)</span>'), $name, number_format_i18n( $avail_roles[$this_role] ) );
			$role_links[$this_role] = "<a href='" . esc_url( add_query_arg( 'role', $this_role, $url ) ) . "'$class>$name</a>";
		}

		return $role_links;
	}

	/**
	 * Retrieve an associative array of bulk actions available on this table.
	 *
	 * @since  3.1.0
	 * @access protected
	 *
	 * @return array Array of bulk actions.
	 */
	protected function get_bulk_actions() {
		$actions = array();

		if ( is_multisite() ) {
			if ( current_user_can( 'remove_users' ) )
				$actions['remove'] = __( 'Remove' );
		} else {
			if ( current_user_can( 'delete_users' ) )
				$actions['delete'] = __( 'Delete' );
		}

		return $actions;
	}

	/**
	 * Output the controls to allow user roles to be changed in bulk.
	 *
	 * @since 3.1.0
	 * @access protected
	 *
	 * @param string $which Whether this is being invoked above ("top")
	 *                      or below the table ("bottom").
	 */
	protected function extra_tablenav( $which ) {
	// XTEC ************ AFEGIT - Hide bulk actions because of the unactive role.
	// 2015.02.15 @vsaavedra
	if ( !is_xtecblocs() || ( (!isset($_REQUEST['status'])) || ( (isset($_REQUEST['status'])) && ($_REQUEST['status'] != 'unactive') ) ) ) {
	// ************ FI
		if ( 'top' != $which )
			return;
		?>
		<div class="alignleft actions">
			<?php if ( current_user_can( 'promote_users' ) ) : ?>
			<label class="screen-reader-text" for="new_role"><?php _e( 'Change role to&hellip;' ) ?></label>
			<select name="new_role" id="new_role">
				<option value=""><?php _e( 'Change role to&hellip;' ) ?></option>
				<?php wp_dropdown_roles(); ?>
			</select>
		<?php
				submit_button( __( 'Change' ), 'button', 'changeit', false );
			endif;

			/**
			 * Fires just before the closing div containing the bulk role-change controls
			 * in the Users list table.
			 *
			 * @since 3.5.0
			 */
			do_action( 'restrict_manage_users' );
			echo '</div>';
	// XTEC ************ AFEGIT - Hide bulk actions because of the unactive role.
	// 2015.02.15 @vsaavedra
		}
	// ************ FI
	}

	/**
	 * Capture the bulk action required, and return it.
	 *
	 * Overridden from the base class implementation to capture
	 * the role change drop-down.
	 *
	 * @since  3.1.0
	 * @access public
	 *
	 * @return string The bulk action required.
	 */
	public function current_action() {
		if ( isset($_REQUEST['changeit']) && !empty($_REQUEST['new_role']) )
			return 'promote';

		return parent::current_action();
	}

	/**
	 * Get a list of columns for the list table.
	 *
	 * @since  3.1.0
	 * @access public
	 *
	 * @return array Array in which the key is the ID of the column,
	 *               and the value is the description.
	 */
	public function get_columns() {
		$c = array(
			'cb'       => '<input type="checkbox" />',
			'username' => __( 'Username' ),
			'name'     => __( 'Name' ),
			'email'    => __( 'E-mail' ),
			'role'     => __( 'Role' ),
			'posts'    => __( 'Posts' )
		);

		if ( $this->is_site_users )
			unset( $c['posts'] );

		return $c;
	}

	/**
	 * Get a list of sortable columns for the list table.
	 *
	 * @since 3.1.0
	 * @access protected
	 *
	 * @return array Array of sortable columns.
	 */
	protected function get_sortable_columns() {
		$c = array(
			'username' => 'login',
			'name'     => 'name',
			'email'    => 'email',
		);

		if ( $this->is_site_users )
			unset( $c['posts'] );

		return $c;
	}

	/**
	 * Generate the list table rows.
	 *
	 * @since 3.1.0
	 * @access public
	 */
	public function display_rows() {
		// XTEC ************ AFEGIT - Hide active users when we want to see unactive users.
		// 2015.02.15 @vsaavedra
		global $wpdb;
		// ************ FI

		// Query the post counts for this page
		if ( ! $this->is_site_users )
			$post_counts = count_many_users_posts( array_keys( $this->items ) );

		$editable_roles = array_keys( get_editable_roles() );

		$style = '';
		// XTEC ************ AFEGIT - Hide active users when we want to see unactive users.
		// 2015.02.15 @vsaavedra
		if ((!isset($_REQUEST['status'])) || ((isset($_REQUEST['status'])) && ($_REQUEST['status'] != 'unactive'))) {
		// ************ FI
			foreach ( $this->items as $userid => $user_object ) {
				if ( count( $user_object->roles ) <= 1 ) {
					$role = reset( $user_object->roles );
				} elseif ( $roles = array_intersect( array_values( $user_object->roles ), $editable_roles ) ) {
					$role = reset( $roles );
				} else {
					$role = reset( $user_object->roles );
				}

				if ( is_multisite() && empty( $user_object->allcaps ) )
					continue;

				// XTEC ************ AFEGIT - Get the arrays of user that already are active in the blog.
				// 2015.02.15 @vsaavedra
				$user_status = __( 'Active' );
				// ************ FI

				$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';

				// XTEC ************ MODIFICAT - Get the arrays of user that already are active in the blog.
				// 2015.02.15 @vsaavedra
				echo "\n\t" . $this->single_row( $user_object, $style, $role, isset( $post_counts ) ? $post_counts[ $userid ] : 0 , $user_status);
				//************ ORIGINAL
                /*
				echo "\n\t" . $this->single_row( $user_object, $style, $role, isset( $post_counts ) ? $post_counts[ $userid ] : 0 );
                */
				// ************ FI
			}
		// XTEC ************ AFEGIT - Hide active users when we want to see unactive users.
		// 2015.02.15 @vsaavedra
		} else if ((isset($_REQUEST['status'])) && ($_REQUEST['status'] == 'unactive')) {
		// ************ FI
			// XTEC ************ AFEGIT - Get the arrays of user that already are active in the blog.
			// 2015.02.15 @vsaavedra
			$usersEmail = array();
			foreach ( $this->items as $userid => $user_object ) {
				$usersEmail[] = $user_object->user_email;
			}
			// ************ FI
			// XTEC ************ AFEGIT - Add the XTEC users who had received an invitation and hasn't already activated it.
			// 2015.02.15 @vsaavedra
			$options = wp_load_alloptions();
			foreach($options as $name=>$value) {
				if(stristr($name, 'new_user')) {
					list($new,$user,$key) = split('_', $name);
					$user_options = unserialize($value);
					$user_info = get_userdata($user_options[user_id]);
					if((!in_array($user_info->user_email, $usersEmail)) && is_a( $user_info, 'WP_User' )) {
						$role = $user_options['role'];
						$usersEmail[] = $user_info->user_email;
						$user_info->type = 'invitacio';
						$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
						$user_status = __( 'Unactive' );
						echo "\n\t" . $this->single_row( $user_info, $style, $role, '-' , $user_status);
					}
				}
			}
			// ************ FI

			// XTEC ************ AFEGIT - Add the non-XTEC users who had received an invitation and hasn't already activated it.
			// 2015.02.15 @vsaavedra
			$currentBlogId = get_current_blog_id();
			$signup = $wpdb->get_results( "SELECT * FROM $wpdb->signups", OBJECT );
			foreach ($signup as $id => $user_object) {
				$meta = unserialize($user_object->meta);
				if((!in_array($user_object->user_email, $usersEmail)) && ($meta['add_to_blog'] == $currentBlogId)) {
					$usersEmail[] = $user_object->user_email;
					$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
					$user_status = __( 'Unactive' );
					$user = new WP_User();
					$data = new StdClass();
					$data->user_login = $user_object->user_login;
					$data->user_email = $user_object->user_email;
					$data->roles = array($meta['new_role']);
					$role = $meta['new_role'];
					$user->data = $data;
					$user->type = 'invitacio';
					echo "\n\t" . $this->single_row( $user, $style, $role, '-' , $user_status);
				}
			}
			// ************ FI
		}
	}

	/**
	 * Generate HTML for a single row on the users.php admin panel.
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @param object $user_object The current user object.
	 * @param string $style       Optional. Style attributes added to the <tr> element.
	 *                            Must be sanitized. Default empty.
	 * @param string $role        Optional. Key for the $wp_roles array. Default empty.
	 * @param int    $numposts    Optional. Post count to display for this user. Defaults
	 *                            to zero, as in, a new user has made zero posts.
	 * @return string Output for a single row.
	 */
	public function single_row( $user_object, $style = '', $role = '', $numposts = 0 ) {
		global $wp_roles;

		if ( !( is_object( $user_object ) && is_a( $user_object, 'WP_User' ) ) )
			$user_object = get_userdata( (int) $user_object );
		$user_object->filter = 'display';
		$email = $user_object->user_email;

		if ( $this->is_site_users )
			$url = "site-users.php?id={$this->site_id}&amp;";
		else
			$url = 'users.php?';

		$checkbox = '';
		// Check if the user for this row is editable
		if ( current_user_can( 'list_users' ) ) {
			// Set up the user editing link
			$edit_link = esc_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), get_edit_user_link( $user_object->ID ) ) );

			// Set up the hover actions for this user
			$actions = array();

		    // XTEC ************ MODIFICAT - 
			// @vsaavedra
			if ( (current_user_can( 'edit_user',  $user_object->ID )) && ($user_object->type != 'invitacio') ) {
			//************ ORIGINAL
            /*
			if ( current_user_can( 'edit_user',  $user_object->ID ) ) {
            */
            //************ FI
				$edit = "<strong><a href=\"$edit_link\">$user_object->user_login</a></strong><br />";

                // XTEC ************ AFEGIT - Do not show edit link for xtecadmin (opening if)
                // 2014.09.03 @aginard
                global $isAgora;
                if ($isAgora) {
                    if (($user_object->user_login != get_xtecadmin_username()) || is_xtecadmin()) {
                        $actions['edit'] = '<a href="' . $edit_link . '">' . __( 'Edit' ) . '</a>';
                    }
                } else {
                //************ FI
                    
                $actions['edit'] = '<a href="' . $edit_link . '">' . __( 'Edit' ) . '</a>';
                
                // XTEC ************ AFEGIT - Do not show edit link for xtecadmin (closing if)
                // 2014.09.03 @aginard
                }
                //************ FI
                
			} else {
				$edit = "<strong>$user_object->user_login</strong><br />";
			}
			if ( !is_multisite() && get_current_user_id() != $user_object->ID && current_user_can( 'delete_user', $user_object->ID ) )

                // XTEC ************ AFEGIT - Do not show delete link for xtecadmin (opening if)
                // 2014.09.03 @aginard
                {
                global $isAgora;
                if ($isAgora) {
                    if ($user_object->user_login != get_xtecadmin_username()) {
        				$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url( "users.php?action=delete&amp;user=$user_object->ID", 'bulk-users' ) . "'>" . __( 'Delete' ) . "</a>";
                    }
                } else {
                //************ FI
				$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url( "users.php?action=delete&amp;user=$user_object->ID", 'bulk-users' ) . "'>" . __( 'Delete' ) . "</a>";
                // XTEC ************ AFEGIT - Do not show delete link for xtecadmin (closing ifs)
                // 2014.09.03 @aginard
                }
                }
                //************ FI
		    // XTEC ************ MODIFICAT - 
			// @vsaavedra
            if ( is_multisite() && get_current_user_id() != $user_object->ID && current_user_can( 'remove_user', $user_object->ID ) && ($user_object->type != 'invitacio'))
			//************ ORIGINAL
            /*
            if ( is_multisite() && get_current_user_id() != $user_object->ID && current_user_can( 'remove_user', $user_object->ID ) )
            */
            //************ FI
				$actions['remove'] = "<a class='submitdelete' href='" . wp_nonce_url( $url."action=remove&amp;user=$user_object->ID", 'bulk-users' ) . "'>" . __( 'Remove' ) . "</a>";

			/**
			 * Filter the action links displayed under each user in the Users list table.
			 *
			 * @since 2.8.0
			 *
			 * @param array   $actions     An array of action links to be displayed.
			 *                             Default 'Edit', 'Delete' for single site, and
			 *                             'Edit', 'Remove' for Multisite.
			 * @param WP_User $user_object WP_User object for the currently-listed user.
			 */
			$actions = apply_filters( 'user_row_actions', $actions, $user_object );
			$edit .= $this->row_actions( $actions );

			// Set up the checkbox ( because the user is editable, otherwise it's empty )
			$checkbox = '<label class="screen-reader-text" for="cb-select-' . $user_object->ID . '">' . sprintf( __( 'Select %s' ), $user_object->user_login ) . '</label>'
						. "<input type='checkbox' name='users[]' id='user_{$user_object->ID}' class='$role' value='{$user_object->ID}' />";

		} else {
			$edit = '<strong>' . $user_object->user_login . '</strong>';
		}
		$role_name = isset( $wp_roles->role_names[$role] ) ? translate_user_role( $wp_roles->role_names[$role] ) : __( 'None' );
		$avatar = get_avatar( $user_object->ID, 32 );

		$r = "<tr id='user-$user_object->ID'$style>";

		list( $columns, $hidden ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";

			switch ( $column_name ) {
				case 'cb':
					$r .= "<th scope='row' class='check-column'>$checkbox</th>";
					break;
				case 'username':
					$r .= "<td $attributes>$avatar $edit</td>";
					break;
				case 'name':
					$r .= "<td $attributes>$user_object->first_name $user_object->last_name</td>";
					break;
				case 'email':
					$r .= "<td $attributes><a href='mailto:$email' title='" . esc_attr( sprintf( __( 'E-mail: %s' ), $email ) ) . "'>$email</a></td>";
					break;
				case 'role':
					$r .= "<td $attributes>$role_name</td>";
					break;
				case 'posts':
					$attributes = 'class="posts column-posts num"' . $style;
					$r .= "<td $attributes>";
					if ( $numposts > 0 ) {
						$r .= "<a href='edit.php?author=$user_object->ID' title='" . esc_attr__( 'View posts by this author' ) . "' class='edit'>";
						$r .= $numposts;
						$r .= '</a>';
					} else {
						$r .= 0;
					}
					$r .= "</td>";
					break;
		        // XTEC ************ AFEGIT - 
			    // @vsaavedra
				case 'user_status':
					$r .= '<td>'.$status.'</td>';
					break;
                //************ FI
				default:
					$r .= "<td $attributes>";

					/**
					 * Filter the display output of custom columns in the Users list table.
					 *
					 * @since 2.8.0
					 *
					 * @param string $output      Custom column output. Default empty.
					 * @param string $column_name Column name.
					 * @param int    $user_id     ID of the currently-listed user.
					 */
					$r .= apply_filters( 'manage_users_custom_column', '', $column_name, $user_object->ID );
					$r .= "</td>";
			}
		}
		$r .= '</tr>';

		return $r;
	}
}
