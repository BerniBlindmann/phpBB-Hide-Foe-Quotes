<?php
/**
 *
 * Hide Foe Quotes. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace hrole\foequotes\event;

use s9e\TextFormatter\Configurator\Items\UnsafeTemplate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hides [quote] blocks written by users on the viewer's foes list.
 *
 * Posts are stored as XML since phpBB 3.2 and rendered on every page view, so the
 * quoted author can be evaluated per viewer at render time. The QUOTE template is
 * wrapped in an xsl:choose that swaps the quote for a short notice whenever the
 * quoted user is on the current viewer's foes list.
 */
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\user */
	protected $user;

	/** @var array|null Cached foes list: user_id => username */
	protected $foes = null;

	/** @var bool Whether this extension's language file has been pulled in defensively */
	protected $lang_loaded = false;

	/**
	 * Constructor
	 *
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param \phpbb\language\language          $language
	 * @param \phpbb\user                       $user
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\language\language $language, \phpbb\user $user)
	{
		$this->db = $db;
		$this->language = $language;
		$this->user = $user;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents()
	{
		return array(
			'core.user_setup'                         => 'load_language_on_setup',
			'core.text_formatter_s9e_configure_after' => 'wrap_quote_template',
			'core.text_formatter_s9e_renderer_setup'  => 'set_renderer_parameters',
			'core.text_formatter_s9e_render_before'   => 'set_renderer_parameters',
		);
	}

	/**
	 * Register this extension's language file.
	 *
	 * @param \phpbb\event\data $event
	 * @return void
	 */
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'hrole/foequotes',
			'lang_set' => 'common',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Wrap the QUOTE template so foe quotes are replaced by a notice.
	 *
	 * This runs once per renderer build; the result is cached by phpBB.
	 *
	 * @param \phpbb\event\data $event
	 * @return void
	 */
	public function wrap_quote_template($event)
	{
		$configurator = $event['configurator'];

		if (!isset($configurator->tags['QUOTE']))
		{
			return;
		}

		$tag = $configurator->tags['QUOTE'];

		// Quotes made with the quote button carry a user_id attribute, which is
		// authoritative. Legacy and hand written quotes only carry the author name,
		// so fall back to a name match for those. The name list is delimited by
		// newlines, which cannot occur inside a phpBB username.
		$condition =
			'@user_id and contains($FOE_QUOTE_IDS, concat(\',\', @user_id, \',\'))'
			. ' or '
			. 'not(@user_id) and @author and contains($FOE_QUOTE_NAMES, concat(\'&#10;\', @author, \'&#10;\'))';

		$notice =
			'<blockquote class="uncited foequotes-hidden"><div>'
			. '<em><xsl:value-of select="$FOE_QUOTE_MESSAGE"/></em>'
			. '</div></blockquote>';

		$template =
			'<xsl:choose>'
			. '<xsl:when test="' . $condition . '">' . $notice . '</xsl:when>'
			. '<xsl:otherwise>' . $tag->template . '</xsl:otherwise>'
			. '</xsl:choose>';

		// The original template is registered as an UnsafeTemplate by phpBB's factory.
		// Reusing that type keeps the style's own markup exempt from the template
		// checker, exactly as it was before wrapping.
		$tag->template = new UnsafeTemplate($template);
	}

	/**
	 * Feed the current viewer's foes list into the renderer.
	 *
	 * @param \phpbb\event\data $event
	 * @return void
	 */
	public function set_renderer_parameters($event)
	{
		$foes = $this->get_foes();

		$ids = '';
		$names = '';

		if (!empty($foes))
		{
			$ids = ',' . implode(',', array_keys($foes)) . ',';
			$names = "\n" . implode("\n", $foes) . "\n";
		}

		$event['renderer']->get_renderer()->setParameters(array(
			'FOE_QUOTE_IDS'     => $ids,
			'FOE_QUOTE_NAMES'   => $names,
			'FOE_QUOTE_MESSAGE' => $this->get_message(),
		));
	}

	/**
	 * Read the current viewer's foes list once per request.
	 *
	 * @return array user_id => username
	 */
	protected function get_foes()
	{
		if ($this->foes !== null)
		{
			return $this->foes;
		}

		$foes = array();

		$user_id = isset($this->user->data['user_id']) ? (int) $this->user->data['user_id'] : ANONYMOUS;

		if ($user_id == ANONYMOUS || empty($this->user->data['is_registered']))
		{
			// Guests have no foes list; do not cache, the user may still be set up.
			return $foes;
		}

		$sql = 'SELECT z.zebra_id, u.username
			FROM ' . ZEBRA_TABLE . ' z, ' . USERS_TABLE . ' u
			WHERE z.user_id = ' . $user_id . '
				AND z.foe = 1
				AND u.user_id = z.zebra_id';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$foes[(int) $row['zebra_id']] = (string) $row['username'];
		}
		$this->db->sql_freeresult($result);

		$this->foes = $foes;

		return $this->foes;
	}

	/**
	 * Text shown in place of a hidden quote.
	 *
	 * @return string
	 */
	protected function get_message()
	{
		if (!$this->lang_loaded && !$this->language->is_set('FOE_QUOTE_HIDDEN'))
		{
			// The renderer is built lazily and can be constructed before
			// core.user_setup has run, so make sure the language file is available
			// rather than printing the raw key.
			$this->language->add_lang('common', 'hrole/foequotes');
			$this->lang_loaded = true;
		}

		return $this->language->lang('FOE_QUOTE_HIDDEN');
	}
}
