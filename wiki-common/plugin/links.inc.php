<?php
// PukiWiki - Yet another WikiWikiWeb clone
// $Id: links.inc.php,v 1.24.4 2011/06/08 20:05:00 Logue Exp $
//
// Update link cache plugin
use PukiWiki\Auth\Auth;
use PukiWiki\Renderer\RendererFactory;
use PukiWiki\Relational;
use PukiWiki\Utility;
use PukiWiki\Router;

// Message setting
function plugin_links_init()
{
	$messages = array(
		'_links_messages'=>array(
			'title_update'  => T_('Cache update'),
			'msg_adminpass' => T_('Administrator password'),
			'btn_submit'    => T_('Exec'),
			'btn_force'		=> T_('Force'),
			'msg_done'      => T_('The update of cashe was completed.'),
			'msg_error'		=> T_('The update of cashe was failure. Please check password.'),
			'msg_usage1'	=> 
				T_('* Content of processing') . "\n" .
				T_(':Cache update|') . "\n" .
				T_('All pages are scanned, whether on which page certain pages have been linked is investigated, and it records in the cache.') ."\n\n" .
				T_('* CAUTION') . "\n" .
				T_('It is likely to drive it for a few minutes in execution.') . "\n" .
				T_('Please wait for a while after pushing the execution button.') . "\n\n",
			'msg_usage2'	=> 
				T_('* EXEC') ."\n" .
				T_('Please input the Administrator password, and click the [Exec] button.') . "\n"
		),
	);
	set_plugin_messages($messages);
}

function plugin_links_action()
{
	global $post, $vars, $foot_explain;
	global $_links_messages, $_string;

	// if (PKWK_READONLY) die_message('PKWK_READONLY prohibits this');
	if (Auth::check_role('readonly')) Utility::dieMessage( $_string['error_prohibit'] );

	$msg   = $_links_messages['title_update'];

	$admin_pass = (empty($post['adminpass'])) ? null : $post['adminpass'];

	if (isset($vars['execute']) && $vars['execute'] === 'true'){
		if (!Auth::check_role('role_contents_admin') || Auth::login($admin_pass) ) {
		//	$force = (isset($post['force']) && $post['force'] === 'on') ? true : false;
			$links = new Relational('');
			$links->init();
			return array('msg'=>$msg, 'body'=>$_links_messages['msg_done']);
		}else{
			$msg = $_links_messages['msg_error'];
		}
	}


	
	$body  = RendererFactory::factory( sprintf($_links_messages['msg_usage1']) );
	$script = Router::get_script_uri();

	if (Auth::check_role('role_contents_admin')) {
		$body .= RendererFactory::factory( sprintf($_links_messages['msg_usage2']) );
	}
	$body .= <<<EOD
<form method="post" action="$script" class="form-inline plugin-links-form">
	<input type="hidden" name="cmd" value="links" />
	<input type="hidden" name="execute" value="true" />
EOD;
	if (Auth::check_role('role_contents_admin')) {
		$body .= <<<EOD
	<div class="form-group">
		<label for="_p_links_adminpass" class="sr-only">{$_links_messages['msg_adminpass']}</label>
		<input type="password" name="adminpass" id="_p_links_adminpass" class="form-control" size="20" value="" placeholder="{$_links_messages['msg_adminpass']}" />
	</div>
EOD;
	}
	$body .= <<<EOD
	<!--div class="checkbox">
		<input type="checkbox" name="force" id="_c_force" />
		<label for="_c_force">{$_links_messages['btn_force']}</label>
	</div-->
	<input type="submit" class="btn btn-primary" value="{$_links_messages['btn_submit']}" />
</form>
EOD;

	return array('msg'=>$msg, 'body'=>$body);
}
/* End of file links.inc.php */
/* Location: ./wiki-common/plugin/links.inc.php */
