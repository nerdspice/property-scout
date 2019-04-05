
<div class="wrap">
<h1><?php esc_html_e('Property Scout Settings', 'prop-scout'); ?></h1>
<?php

if (isset($_POST['submit'])):
  check_admin_referer('prop_scout_update_settings', 'prop_scout_update_settings');
  if (!current_user_can('manage_options')) {
    die(esc_html__("You don't have adequate permission to edit settings.", 'prop-scout'));
  }

  $zillow_zwsid = sanitize_text_field($_POST['zillow_zwsid']);
  $this->setOption('zillow_zwsid', $zillow_zwsid);

?>
  <div class="updated notice is-dismissible">
    <p><?php esc_html_e('Settings Updated', 'prop-scout'); ?>.</p>
  </div>
<?php
endif; // isset($_POST['submit'])

$options = $this->getOptions();
$zillow_zwsid = trim(@$options['zillow_zwsid']);
?>
<form id="prop_scout_settings" method="post">
<?php wp_nonce_field('prop_scout_update_settings', 'prop_scout_update_settings'); ?>

<table class="form-table"><tbody>
  <tr>
    <th scope="row">
      <label for="zillow_zwsid"><?php esc_html_e('Zillow API ZWSID', 'prop-scout'); ?></label>
    </th>
    <td>
      <input type="text" name="zillow_zwsid" id="zillow_zwsid" style="width:350px;" value="<?php echo esc_attr($zillow_zwsid); ?>" />
      <br />
		  <span><?php esc_html_e('', 'prop-scout'); ?></span>
    </td>
  </tr>
</tbody></table>

<p class="submit" style="text-align: left;">
  <input type="submit" name="submit" value="<?php esc_html_e('Save Settings', 'prop-scout'); ?>" class="button-primary" id="save" />
</p>
</form>
</div> <!-- .wrap -->
