<?php
/**
 * @var string[][] $vars
 */
?>
<table class="tfa-admin-tpl-vars">
    <tbody>
	<?php foreach ( $vars as $var_name => $var ) { ?>
        <tr class="<?php echo empty( $var['required'] ) ? '' : 'required' ?>">
            <td class="tfa-var-name"><?php echo $var_name ?></td>
            <td class="tfa-var-desc"><?php echo $var['desc'] ?></td>
        </tr>
	<?php } ?>
    </tbody>
</table>