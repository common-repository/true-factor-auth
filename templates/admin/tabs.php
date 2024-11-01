<?php
/**
 * @var array $admin_pages
 * @var string $admin_page
 */
?>
<h2 class="nav-tab-wrapper">
	<?php foreach ( $admin_pages as $slug => $tab ) { ?>
        <a href="<?php menu_page_url( $slug ) ?>" class="nav-tab <?php echo ( $_GET['page'] == $slug ) ? 'nav-tab-active' : '' ?>"><?php echo $tab['menu_title'] ??
		                                                                                                                                      $tab['title'] ?></a>
	<?php } ?>
</h2>
