<?php
namespace Arshid6Social\Engagement\Features;

/**
 * External Social Share feature.
 *
 * Adds share-to-external-network buttons on activity posts.
 * Supports 80+ networks. All share URLs are generated client-side.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Social_Share_External {

	// ── Network registry ──────────────────────────────────────────────────────

	/**
	 * Returns all supported networks.
	 * Each entry: label, color (hex), url ({URL}/{TITLE} placeholders),
	 * optional: action ('copy'|'print'|'native'|'wechat'), target ('_self').
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function networks(): array {
		return array(
			'facebook'         => array( 'label' => 'Facebook',         'color' => '#1877f2', 'url' => 'https://www.facebook.com/sharer/sharer.php?u={URL}' ),
			'x'                => array( 'label' => 'X',                'color' => '#000000', 'url' => 'https://twitter.com/intent/tweet?text={TITLE}&url={URL}' ),
			'twitter'          => array( 'label' => 'Twitter',          'color' => '#1da1f2', 'url' => 'https://twitter.com/intent/tweet?text={TITLE}&url={URL}' ),
			'whatsapp'         => array( 'label' => 'WhatsApp',         'color' => '#25d366', 'url' => 'https://api.whatsapp.com/send?text={TITLE}%20{URL}' ),
			'telegram'         => array( 'label' => 'Telegram',         'color' => '#2ca5e0', 'url' => 'https://t.me/share/url?url={URL}&text={TITLE}' ),
			'linkedin'         => array( 'label' => 'LinkedIn',         'color' => '#0a66c2', 'url' => 'https://www.linkedin.com/sharing/share-offsite/?url={URL}' ),
			'reddit'           => array( 'label' => 'Reddit',           'color' => '#ff4500', 'url' => 'https://reddit.com/submit?url={URL}&title={TITLE}' ),
			'email'            => array( 'label' => 'Email',            'color' => '#7a7a7a', 'url' => 'mailto:?subject={TITLE}&body={URL}', 'target' => '_self' ),
			'copy_link'        => array( 'label' => 'Copy Link',        'color' => '#555555', 'url' => '', 'action' => 'copy' ),
			'threads'          => array( 'label' => 'Threads',          'color' => '#000000', 'url' => 'https://www.threads.net/intent/post?text={TITLE}%20{URL}' ),
			'bluesky'          => array( 'label' => 'Bluesky',          'color' => '#0085ff', 'url' => 'https://bsky.app/intent/compose?text={TITLE}%20{URL}' ),
			'mastodon'         => array( 'label' => 'Mastodon',         'color' => '#6364ff', 'url' => 'https://toot.kytta.dev/?text={TITLE}%20{URL}' ),
			'pinterest'        => array( 'label' => 'Pinterest',        'color' => '#e60023', 'url' => 'https://pinterest.com/pin/create/button/?url={URL}&description={TITLE}' ),
			'tumblr'           => array( 'label' => 'Tumblr',           'color' => '#35465c', 'url' => 'https://www.tumblr.com/share/link?url={URL}&name={TITLE}' ),
			'vk'               => array( 'label' => 'VK',               'color' => '#0077ff', 'url' => 'https://vk.com/share.php?url={URL}&title={TITLE}' ),
			'viber'            => array( 'label' => 'Viber',            'color' => '#7360f2', 'url' => 'viber://forward?text={TITLE}%20{URL}', 'target' => '_self' ),
			'line'             => array( 'label' => 'Line',             'color' => '#00c300', 'url' => 'https://social-plugins.line.me/lineit/share?url={URL}' ),
			'snapchat'         => array( 'label' => 'Snapchat',         'color' => '#fffc00', 'url' => 'https://www.snapchat.com/scan?attachmentUrl={URL}' ),
			'skype'            => array( 'label' => 'Skype',            'color' => '#00aff0', 'url' => 'https://web.skype.com/share?url={URL}&text={TITLE}' ),
			'messenger'        => array( 'label' => 'Messenger',        'color' => '#0084ff', 'url' => 'https://www.facebook.com/dialog/send?link={URL}&app_id=291494419107&redirect_uri={URL}' ),
			'pocket'           => array( 'label' => 'Pocket',           'color' => '#ef3f56', 'url' => 'https://getpocket.com/edit?url={URL}&title={TITLE}' ),
			'evernote'         => array( 'label' => 'Evernote',         'color' => '#00a82d', 'url' => 'https://www.evernote.com/clip.action?url={URL}&title={TITLE}' ),
			'flipboard'        => array( 'label' => 'Flipboard',        'color' => '#e12828', 'url' => 'https://share.flipboard.com/bookmarklet/popout?v=2&title={TITLE}&url={URL}' ),
			'buffer'           => array( 'label' => 'Buffer',           'color' => '#168de2', 'url' => 'https://buffer.com/add?url={URL}&title={TITLE}' ),
			'trello'           => array( 'label' => 'Trello',           'color' => '#0052cc', 'url' => 'https://trello.com/add-card?source=mode&url={URL}&name={TITLE}' ),
			'teams'            => array( 'label' => 'Teams',            'color' => '#6264a7', 'url' => 'https://teams.microsoft.com/share?href={URL}&msgText={TITLE}' ),
			'hacker_news'      => array( 'label' => 'Hacker News',      'color' => '#ff6600', 'url' => 'https://news.ycombinator.com/submitlink?u={URL}&t={TITLE}' ),
			'digg'             => array( 'label' => 'Digg',             'color' => '#000000', 'url' => 'https://digg.com/submit?phase=2&url={URL}&title={TITLE}' ),
			'instapaper'       => array( 'label' => 'Instapaper',       'color' => '#000000', 'url' => 'https://www.instapaper.com/hello2?url={URL}&title={TITLE}' ),
			'gmail'            => array( 'label' => 'Gmail',            'color' => '#ea4335', 'url' => 'https://mail.google.com/mail/?view=cm&to=&su={TITLE}&body={URL}' ),
			'yahoo_mail'       => array( 'label' => 'Yahoo Mail',       'color' => '#7b0099', 'url' => 'https://compose.mail.yahoo.com/?subject={TITLE}&body={URL}' ),
			'outlook'          => array( 'label' => 'Outlook.com',      'color' => '#0078d4', 'url' => 'https://outlook.live.com/owa/?path=/mail/action/compose&subject={TITLE}&body={URL}' ),
			'aol_mail'         => array( 'label' => 'AOL Mail',         'color' => '#ff0b00', 'url' => 'https://mail.aol.com/mail/compose-message.aspx?subject={TITLE}&body={URL}' ),
			'message'          => array( 'label' => 'SMS',              'color' => '#07c160', 'url' => 'sms:?body={TITLE}%20{URL}', 'target' => '_self' ),
			'sina_weibo'       => array( 'label' => 'Sina Weibo',       'color' => '#e6162d', 'url' => 'https://service.weibo.com/share/share.php?url={URL}&title={TITLE}' ),
			'odnoklassniki'    => array( 'label' => 'Odnoklassniki',    'color' => '#ed812b', 'url' => 'https://connect.ok.ru/offer?url={URL}&title={TITLE}' ),
			'xing'             => array( 'label' => 'XING',             'color' => '#006567', 'url' => 'https://www.xing.com/spi/shares/new?sc_p=xing-share&url={URL}' ),
			'mewe'             => array( 'label' => 'MeWe',             'color' => '#003399', 'url' => 'https://mewe.com/share?link={URL}' ),
			'wordpress_com'    => array( 'label' => 'WordPress',        'color' => '#21759b', 'url' => 'https://wordpress.com/press-this.php?u={URL}&t={TITLE}' ),
			'diaspora'         => array( 'label' => 'Diaspora',         'color' => '#191919', 'url' => 'https://share.diasporafoundation.org/?title={TITLE}&url={URL}' ),
			'pinboard'         => array( 'label' => 'Pinboard',         'color' => '#0000e0', 'url' => 'https://pinboard.in/add?url={URL}&title={TITLE}' ),
			'hatena'           => array( 'label' => 'Hatena',           'color' => '#00a4de', 'url' => 'https://b.hatena.ne.jp/add?mode=confirm&url={URL}&title={TITLE}' ),
			'mix'              => array( 'label' => 'Mix',              'color' => '#f50000', 'url' => 'https://mix.com/add?url={URL}' ),
			'plurk'            => array( 'label' => 'Plurk',            'color' => '#cf682f', 'url' => 'https://www.plurk.com/?qualifier=shares&status={TITLE}%20{URL}' ),
			'stocktwits'       => array( 'label' => 'StockTwits',       'color' => '#40a829', 'url' => 'https://stocktwits.com/transmit?body={TITLE}%20{URL}' ),
			'blogger'          => array( 'label' => 'Blogger',          'color' => '#f57d00', 'url' => 'https://www.blogger.com/blog-this.g?u={URL}&n={TITLE}' ),
			'typepad'          => array( 'label' => 'TypePad',          'color' => '#000000', 'url' => 'https://www.typepad.com/services/quickpost/create?qp_url={URL}&qp_title={TITLE}' ),
			'diigo'            => array( 'label' => 'Diigo',            'color' => '#4da2dd', 'url' => 'https://www.diigo.com/post?url={URL}&title={TITLE}' ),
			'livejournal'      => array( 'label' => 'LiveJournal',      'color' => '#00b0ea', 'url' => 'https://www.livejournal.com/update.bml?subject={TITLE}&event={URL}' ),
			'mail_ru'          => array( 'label' => 'Mail.Ru',          'color' => '#005ff9', 'url' => 'https://connect.mail.ru/share?url={URL}&title={TITLE}' ),
			'mendeley'         => array( 'label' => 'Mendeley',         'color' => '#a6001a', 'url' => 'https://www.mendeley.com/import/?url={URL}&title={TITLE}' ),
			'meneame'          => array( 'label' => 'Meneame',          'color' => '#f16529', 'url' => 'https://meneame.net/submit.php?url={URL}' ),
			'micro_blog'       => array( 'label' => 'Micro.blog',       'color' => '#eb8227', 'url' => 'https://micro.blog/?url={URL}&title={TITLE}' ),
			'myspace'          => array( 'label' => 'MySpace',          'color' => '#313131', 'url' => 'https://myspace.com/post?u={URL}&t={TITLE}' ),
			'qzone'            => array( 'label' => 'Qzone',            'color' => '#ffcc00', 'url' => 'https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url={URL}&title={TITLE}' ),
			'kakao'            => array( 'label' => 'Kakao',            'color' => '#f7e600', 'url' => 'https://story.kakao.com/share?url={URL}' ),
			'raindrop'         => array( 'label' => 'Raindrop.io',      'color' => '#0176d3', 'url' => 'https://app.raindrop.io/add?link={URL}&title={TITLE}' ),
			'refind'           => array( 'label' => 'Refind',           'color' => '#1da1f2', 'url' => 'https://refind.com/add?url={URL}&title={TITLE}' ),
			'folkd'            => array( 'label' => 'Folkd',            'color' => '#77bbff', 'url' => 'https://www.folkd.com/submit/{URL}' ),
			'slashdot'         => array( 'label' => 'Slashdot',         'color' => '#000000', 'url' => 'https://slashdot.org/index.pl?op=newstory&url={URL}&title={TITLE}' ),
			'svejo'            => array( 'label' => 'Svejo',            'color' => '#72b738', 'url' => 'https://svejo.net/add?url={URL}&title={TITLE}' ),
			'symbaloo'         => array( 'label' => 'Symbaloo',         'color' => '#00a3e0', 'url' => 'https://www.symbaloo.com/add/tile?source=3&title={TITLE}&url={URL}' ),
			'threema'          => array( 'label' => 'Threema',          'color' => '#4caf50', 'url' => 'https://threema.id/compose?text={TITLE}%20{URL}', 'target' => '_self' ),
			'wykop'            => array( 'label' => 'Wykop',            'color' => '#ff5917', 'url' => 'https://wykop.pl/dodaj/link/?url={URL}&title={TITLE}' ),
			'yummly'           => array( 'label' => 'Yummly',           'color' => '#e6472a', 'url' => 'https://www.yummly.com/recipe/?url={URL}&title={TITLE}' ),
			'douban'           => array( 'label' => 'Douban',           'color' => '#2ea131', 'url' => 'https://www.douban.com/recommend/?url={URL}&title={TITLE}' ),
			'draugiem'         => array( 'label' => 'Draugiem',         'color' => '#ff6600', 'url' => 'https://www.draugiem.lv/say/ext/add.php?link={URL}&title={TITLE}' ),
			'fark'             => array( 'label' => 'Fark',             'color' => '#000000', 'url' => 'https://cgi.fark.com/cgi/fark/submit.pl?new_url={URL}&new_comment={TITLE}' ),
			'google_classroom' => array( 'label' => 'Google Classroom', 'color' => '#4caf50', 'url' => 'https://classroom.google.com/u/0/share?url={URL}' ),
			'google_translate' => array( 'label' => 'Google Translate', 'color' => '#4285f4', 'url' => 'https://translate.google.com/translate?u={URL}' ),
			'houzz'            => array( 'label' => 'Houzz',            'color' => '#7ac142', 'url' => 'https://www.houzz.com/imageClipperUpload?link={URL}&title={TITLE}' ),
			'amazon_wishlist'  => array( 'label' => 'Amazon Wish List', 'color' => '#ff9900', 'url' => 'https://www.amazon.com/wishlist/add-to-cart?asin=&u={URL}' ),
			'balatarin'        => array( 'label' => 'Balatarin',        'color' => '#1f5f8b', 'url' => 'https://www.balatarin.com/links/submit?url={URL}&title={TITLE}' ),
			'bibsonomy'        => array( 'label' => 'BibSonomy',        'color' => '#6c6c6c', 'url' => 'https://www.bibsonomy.org/BibtexHandler?requTask=upload&url={URL}&description={TITLE}' ),
			'blogmarks'        => array( 'label' => 'BlogMarks',        'color' => '#7c3a8e', 'url' => 'https://blogmarks.net/my/new.php?mini=1&url={URL}&title={TITLE}' ),
			'bookmarks_fr'     => array( 'label' => 'Bookmarks.fr',     'color' => '#0c63a2', 'url' => 'https://www.bookmarks.fr/index.php?action=addlink&burl={URL}&btitle={TITLE}' ),
			'box_net'          => array( 'label' => 'Box.net',          'color' => '#0061d5', 'url' => 'https://app.box.com/index.php?rm=box_v2_widget_thirdparty_sidebar&url={URL}' ),
			'diary_ru'         => array( 'label' => 'Diary.Ru',         'color' => '#86bb5e', 'url' => 'https://www.diary.ru/?newpost&addurl&url={URL}&headline={TITLE}' ),
			'known'            => array( 'label' => 'Known',            'color' => '#a67c52', 'url' => 'https://known.to/?url={URL}&title={TITLE}' ),
			'mixi'             => array( 'label' => 'Mixi',             'color' => '#d1a72a', 'url' => 'https://mixi.jp/share.pl?u={URL}&t={TITLE}' ),
			'papaly'           => array( 'label' => 'Papaly',           'color' => '#5ba7e0', 'url' => 'https://papaly.com/api/share/?url={URL}&title={TITLE}' ),
			'print'            => array( 'label' => 'Print',            'color' => '#737373', 'url' => '', 'action' => 'print' ),
			'printfriendly'    => array( 'label' => 'PrintFriendly',    'color' => '#009b00', 'url' => 'https://www.printfriendly.com/print?url={URL}' ),
			'pusha'            => array( 'label' => 'Pusha',            'color' => '#000000', 'url' => 'https://pusha.se/pushitreal?url={URL}&title={TITLE}' ),
			'push_to_kindle'   => array( 'label' => 'Push to Kindle',   'color' => '#333333', 'url' => 'https://fivefilters.org/kindle-it/send.php?url={URL}' ),
			'rediff'           => array( 'label' => 'Rediff MyPage',    'color' => '#e53e3e', 'url' => 'https://bookmarks.rediff.com/bookmarks/bookmarks.rediffaddbookmark?newURL={URL}&Title={TITLE}' ),
			'sitejot'          => array( 'label' => 'SiteJot',          'color' => '#ffa500', 'url' => 'https://www.sitejot.com/bookmark.sfly?action=add&url={URL}&title={TITLE}' ),
			'twiddla'          => array( 'label' => 'Twiddla',          'color' => '#000000', 'url' => 'https://www.twiddla.com/CreateMeeting.aspx?meetingURL={URL}&meetingTitle={TITLE}' ),
			'wechat'           => array( 'label' => 'WeChat',           'color' => '#07c160', 'url' => '', 'action' => 'wechat' ),
			'send_dm'          => array( 'label' => 'Send as Message',  'color' => '#2563eb', 'url' => '', 'action' => 'send_dm' ),
		);
	}

	// ── Default network selection (pre-checked in admin) ──────────────────────

	public static function default_networks(): array {
		return array( 'facebook', 'x', 'whatsapp', 'telegram', 'linkedin', 'reddit', 'email', 'copy_link', 'send_dm', 'twitter', 'threads', 'bluesky', 'pinterest', 'pocket', 'viber', 'line' );
	}

	// ── Constructor ───────────────────────────────────────────────────────────

	public function __construct() {
		add_filter( 'arshid6social_format_activity', array( $this, 'add_share_data' ), 10, 2 );
	}

	/**
	 * Appends extShareUrl / extShareTitle to each formatted activity so the JS
	 * can build share links without a server round-trip.
	 */
	public function add_share_data( array $formatted, object $activity ): array {
		if ( 'activity_comment' === ( $activity->type ?? '' ) ) {
			return $formatted;
		}

		$url   = $formatted['permalink'] ?? $formatted['primaryLink'] ?? home_url( '/' );
		$raw   = wp_strip_all_tags( $formatted['content'] ?? '' );
		$title = mb_substr( $raw, 0, 200 );

		$formatted['extShareUrl']   = esc_url( $url );
		$formatted['extShareTitle'] = esc_attr( $title );

		return $formatted;
	}

	// ── Helper: build the JS network map for localization ─────────────────────

	/**
	 * Returns only the enabled networks as a JS-ready array.
	 *
	 * @param  array $enabled_keys Network keys enabled by admin.
	 * @return array<string, array<string,string>>
	 */
	public static function enabled_networks_for_js( array $enabled_keys ): array {
		$all    = self::networks();
		$result = array();
		foreach ( $enabled_keys as $key ) {
			if ( isset( $all[ $key ] ) ) {
				$result[ $key ] = $all[ $key ];
			}
		}
		return $result;
	}
}
