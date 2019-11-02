<?php
/**
*
* @package phpBB Extension - LMDI Glossary extension
* @copyright (c) 2015-2019 LMDI - Pierre Duhem
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace lmdi\gloss\core;

class glossnew
{
	protected $template;
	protected $user;
	protected $language;
	protected $db;
	protected $ext_manager;
	protected $path_helper;
	protected $helper;
	protected $config;
	protected $cache;
	protected $files_factory;
	protected $gloss_helper;
	protected $phpEx;
	protected $phpbb_root_path;
	protected $glossary_table;
	protected $ext_path;
	protected $ext_path_web;

	public function __construct(
		\phpbb\template\template $template,
		\phpbb\language\language $language,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\extension\manager $ext_manager,
		\phpbb\path_helper $path_helper,
		\phpbb\controller\helper $helper,
		\phpbb\config\config $config,
		\phpbb\request\request $request,
		\phpbb\cache\service $cache,
		\lmdi\gloss\core\helper $gloss_helper,
		$phpEx,
		$phpbb_root_path,
		$glossary_table,
		\phpbb\files\factory $files_factory = null
		)
	{
		$this->template 		= $template;
		$this->language		= $language;
		$this->db 			= $db;
		$this->ext_manager		= $ext_manager;
		$this->path_helper		= $path_helper;
		$this->helper			= $helper;
		$this->config			= $config;
		$this->request			= $request;
		$this->cache			= $cache;
		$this->gloss_helper		= $gloss_helper;
		$this->phpEx 			= $phpEx;
		$this->phpbb_root_path 	= $phpbb_root_path;
		$this->glossary_table 	= $glossary_table;

		if ($files_factory)
		{
			$this->files_factory = $files_factory;
		}

		$this->ext_path = $this->ext_manager->get_extension_path('lmdi/gloss', true);
		$this->ext_path_web = $this->path_helper->update_web_root_path($this->ext_path);
	}

	public $u_action;

	public function main()
	{
		$action = 'edit';
		$num		= $this->request->variable('code', 0);
		$delete	= $this->request->variable('delete', "rien");
		$save	= $this->request->variable('save', "rien");
		if ($delete != 'rien')
		{
			$action = 'delete';
		}
		if ($save != 'rien')
		{
			$action = 'save';
		}

		// var_dump ($action);

		switch ($action)
		{
			default :		// Item creation
				// Breadcrumbs
				$this->template->assign_block_vars('navlinks', array(
					'U_VIEW_FORUM'	=> $str_glossedit,
					'FORUM_NAME'	=> $this->language->lang('GLOSS_CREAT'),
				));

				$lang = $lg = $this->gloss_helper->get_def_language($this->glossary_table, 'lang');
				$action = $this->helper->route('lmdi_gloss_controller', array('mode' => 'glossnew'));
				$this->template->assign_vars(array(
					'TITLE'		=> $this->language->lang('GLOSS_CREAT'),
					'ACTION'		=> $action,
					'CODE'		=> 0,
					'VARI'		=> '',
					'TERM'		=> '',
					'DESC'		=> '',
					'CAT'		=> '',
					'ILINKS'		=> '',
					'ELINKS'		=> '',
					'LABEL'		=> '',
					'LANG'		=> $lang,
					'PICT'		=> '',
					'S_EDIT'		=> 0,		// 0 = creation, 1 = edition
					'S_PICT'		=> 0,
					));

				$titre = $this->language->lang('GLOSS_CREAT');
				page_header($titre);
				$this->template->set_filenames (array(
					'body' => 'glossform.html',
				));
				page_footer();
			break;
		case 'save' :
			$term_id = $this->db->sql_escape(trim($this->request->variable('term_id', 0)));
			$term = $this->db->sql_escape(trim($this->request->variable('term',"",true)));
			$variants = $this->db->sql_escape(trim($this->request->variable('vari',"",true)));
			$descript = $this->db->sql_escape(trim($this->request->variable('desc',"",true)));
			if (mb_strlen($descript) > 511)
			{
				$descript = mb_substr($descript, 0, 511);
			}
			$cat = $this->db->sql_escape(trim($this->request->variable('cat',"",true)));
			$ilinks = $this->db->sql_escape(trim($this->request->variable('ilinks',"",true)));
			$elinks = $this->db->sql_escape(trim($this->request->variable('elinks',"",true)));
			$label = $this->db->sql_escape(trim($this->request->variable('label',"",true)));
			$lang = $this->db->sql_escape($this->request->variable('lang',"fr",true));
			$coche = $this->request->variable('upload', "", true);
			$picture = '';
			switch ($coche)
			{
				case "existe":
					$picture = $this->request->variable('pict', "", true);
				break;
				case "noup":
				break;
				case "reuse":
					$picture = $this->request->variable('reuse', "", true);
				break;
				case "nouv":
					$errors = array();
					$picture = $this->upload_32x($errors);
					if (!$picture)
					{
						$nb = count($errors);
						$message = "";
						for ($i = 0; $i < $nb; $i++)
						{
							$message .= $errors[$i];
							$message .= "<br>";
						}
						$message .= $this->language->lang('LMDI_CLICK_BACK');
						trigger_error($message, E_USER_WARNING);
					}
					else
					{
						$picture = $this->db->sql_escape($picture);
					}
				break;
			}	// Inner switch
			$sql = "INSERT INTO " . $this->glossary_table . "
				(variants, term, description, cat, ilinks, elinks, label, picture, lang) 
				VALUES (\"$variants\", \"$term\", \"$descript\", \"$cat\", \"$ilinks\", 
				'$elinks', \"$label\", \"$picture\", \"$lang\")";
			$this->db->sql_query($sql);
			$term_id = $this->db->sql_nextid();

			// Purge the cache
			$this->cache->destroy('_gloss_table');

			// Information message et redirection
			$params = "mode=glossadmin&code=$term_id";
			$url = append_sid($this->phpbb_root_path . 'app.' . $this->phpEx . '/gloss', $params);
			$url .= "#$term_id"; // Anchor target = term_id
			$url = "<a href=\"$url\">";
			$message = sprintf ($this->language->lang('GLOSS_ED_SAVE'), $term, $url, '</a>');
			trigger_error($message);
			break;
		}	// Outer switch
	}
}
