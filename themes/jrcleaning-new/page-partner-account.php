<?php
/**
 * Template Name: Partner Account Features
 *
 * What a partner actually gets once they are set up: the tour of the My Account area.
 *
 * The programme has been sold on its rates — the tier table on /partners/ does that job.
 * But the account area is the part that removes work from a branch manager's day, and
 * nothing on the public site mentions it exists. A branch that does not know there is a
 * calendar, a report library and a request form rings up instead, which is the cost this
 * page is meant to remove.
 *
 * ACCURACY. Every claim below is checked against what the account area does today:
 *
 *   - Money is deliberately absent. jrc_affiliate_show_money() returns false, so
 *     partners do not currently see job values or reward figures. Promising a figure
 *     they cannot see would be the worst possible first impression.
 *   - Features are listed in the same order as jrc_affiliate_tabs(), so the page and
 *     the navigation a partner meets afterwards agree with each other.
 *
 * If a tab is ever added or removed, this page needs revisiting — there is no way to
 * generate marketing copy from a tab registry, and a wrong tour is worse than none.
 *
 * @package jrcleaning
 */

get_header();

$account_url  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
$register_url = home_url( '/partners/register/' );
$is_partner   = function_exists( 'jrc_is_affiliate' ) && jrc_is_affiliate();
$logged_in    = is_user_logged_in();

/**
 * The tour. Ordered to match jrc_affiliate_tabs().
 *
 * `icon` is an inline SVG path set rather than a file: six small line icons are cheaper
 * inline than six HTTP requests, and they inherit currentColor so they follow the card's
 * hover state without a second asset.
 */
$features = apply_filters( 'jrc_partner_account_features', array(
	array(
		'tab'   => 'Bonus Program',
		'title' => 'See where your branch stands',
		'body'  => 'Your current tier and the date you reached it, how many bookings you have in the last twelve months, and exactly how many more take you to the next tier. Your referral code and a ready-written message sit here too, so passing it to a landlord or tenant takes one copy and paste.',
		'points' => array(
			'Progress to the next tier, counted for you',
			'Fair warning before a booking ages out of your twelve-month count',
			'Your referral link, with the code applied automatically',
		),
		'icon'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
	),
	array(
		'tab'   => 'Request a clean',
		'title' => 'Book a clean without picking up the phone',
		'body'  => 'Tell us the property, what is needed and when, and it goes straight into our jobs list. You get a confirmation by email and it appears on your calendar as Requested until we confirm the slot.',
		'points' => array(
			'Dates we are full are closed off before you submit',
			'If a day is full, the next free ones are offered',
			'Add a contact at the property so we can arrange access',
		),
		'icon'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M12 15v4M10 17h4"/>',
	),
	array(
		'tab'   => 'Reports',
		'title' => 'Every photo report, in one place',
		'body'  => 'We issue a photo report after every clean — what was covered, with pictures. They collect here rather than scattering across email, so you can forward one to a landlord or tenant months later without hunting for it.',
		'points' => array(
			'New reports are counted on the menu so nothing is missed',
			'Filter by month, service or address',
			'Download a whole month at once as a zip',
		),
		'icon'  => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
	),
	array(
		'tab'   => 'Calendar',
		'title' => 'Your cleans in the diary you already use',
		'body'  => 'A month view of everything booked for your branch. Open a clean to see the address, what it covers and how it has progressed — requested, confirmed, done. Subscribe once and the whole lot appears in Outlook, Google or Apple Calendar and keeps itself up to date.',
		'points' => array(
			'A progress history on every clean, so "is it booked yet?" is already answered',
			'Add a single clean to your diary, or subscribe to all of them',
			'The subscription updates itself as the schedule changes',
		),
		'icon'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/><path d="M8 15h.01M12 15h.01M16 15h.01"/>',
	),
	array(
		'tab'   => 'Referred jobs',
		'title' => 'The full history, whenever you need it',
		'body'  => 'Every clean booked by your branch or through your code, newest first, with its status and what it covered. Useful at renewal time, and useful when somebody asks what was done at a property eight months ago.',
		'points' => array(
			'Search and page through your whole history',
			'Open any job to see how its status changed, and when',
			'Export the lot to a spreadsheet',
		),
		'icon'  => '<path d="M4 6h16M4 12h16M4 18h10"/><circle cx="19" cy="18" r="2"/>',
	),
	array(
		'tab'   => 'Price list',
		'title' => 'Your prices, with your rates already applied',
		'body'  => 'The price list for your account — every service, guide prices by property size for end of tenancy and sparkle cleans, and add-ons — with your agreed rates worked in. It always reflects your current pricing, so there is no out-of-date PDF doing the rounds.',
		'points' => array(
			'Your discount applied to every line, nothing to work out',
			'Download a branded PDF to send on to landlords and tenants',
			'Updates itself whenever your rates change',
		),
		'icon'  => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
	),
	array(
		'tab'   => 'Account details',
		'title' => 'Set it up once, to suit your office',
		'body'  => 'Your branch details, and control over which emails we send. If reports and summaries should land in a shared lettings inbox rather than your personal one, point them there and they will follow.',
		'points' => array(
			'Send reports to a shared inbox instead',
			'Choose which notifications you get, individually',
			'Turn the monthly summary on or off',
		),
		'icon'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
	),
) );
?>

<section class="section--tight" style="padding-bottom:0;">
	<div class="primary-container">
		<nav class="inner-hero__crumbs" aria-label="Breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
			<span class="sep">›</span>
			<a href="<?php echo esc_url( home_url( '/partners/' ) ); ?>">Partners</a>
			<span class="sep">›</span>
			<span aria-current="page">Your account</span>
		</nav>
	</div>
</section>

<!-- hero -->
<section class="section--tight">
	<div class="primary-container pa-hero">
		<div class="pa-hero__intro">
			<span class="tag">For partners</span>
			<h1>Everything your branch needs, <span class="text-accent">in one account</span></h1>
			<p class="lead">
				Partners get more than a better rate. Every clean you book, every photo report we
				issue and every date in the diary lives in your own account area &mdash; so the
				answer to "where is that report?" or "is Friday confirmed?" is a login away rather
				than a phone call.
			</p>

			<div class="pa-hero__buttons">
				<?php if ( $is_partner ) : ?>
					<a href="<?php echo esc_url( $account_url ); ?>" class="button button--primary">Go to your account →</a>
				<?php elseif ( $logged_in ) : ?>
					<a href="<?php echo esc_url( $register_url ); ?>" class="button button--primary">Join the programme →</a>
					<a href="<?php echo esc_url( $account_url ); ?>" class="button button--outline">Your account</a>
				<?php else : ?>
					<a href="<?php echo esc_url( $register_url ); ?>" class="button button--primary">Become a partner →</a>
					<a href="<?php echo esc_url( $account_url ); ?>" class="button button--outline">Sign in</a>
				<?php endif; ?>
			</div>
		</div>

		<aside class="pa-hero__aside">
			<div class="pa-hero__aside-eyebrow">Included with every partner account</div>
			<ul>
				<li><span>✓</span>No extra cost, and nothing to install</li>
				<li><span>✓</span>Set up the moment your branch is approved</li>
				<li><span>✓</span>Works on a phone, at a desk, or in your own calendar app</li>
			</ul>
		</aside>
	</div>
</section>

<!-- the tour -->
<section class="section">
	<div class="primary-container">
		<div class="section-head is-center">
			<h2>What is in your account</h2>
			<p>Six areas, in the order you will find them once you are signed in.</p>
		</div>

		<div class="pa-grid">
			<?php foreach ( $features as $i => $f ) : ?>
				<article class="pa-card">
					<span class="pa-card__icon" aria-hidden="true">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
							stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
							<?php echo $f['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG paths defined above. ?>
						</svg>
					</span>
					<span class="pa-card__tab"><?php echo esc_html( $f['tab'] ); ?></span>
					<h3><?php echo esc_html( $f['title'] ); ?></h3>
					<p><?php echo esc_html( $f['body'] ); ?></p>
					<ul class="pa-card__points">
						<?php foreach ( $f['points'] as $point ) : ?>
							<li><?php echo esc_html( $point ); ?></li>
						<?php endforeach; ?>
					</ul>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- privacy -->
<section class="section section--paper">
	<div class="primary-container">
		<div class="pa-split">
			<div class="pa-split__content">
				<span class="tag">Kept private</span>
				<h2>Your reports are not sitting on a public link</h2>
				<p>
					A photo report shows the inside of somebody's home and names the address it was
					taken at. We treat it that way.
				</p>
				<ul class="pa-split__points">
					<li>Reports are never emailed as an attachment or put on a guessable web address</li>
					<li>Each one is stored under a random filename, outside anything a search engine can reach</li>
					<li>Downloading one checks it belongs to your branch first, every time</li>
					<li>Only your own branch's work is ever visible in your account</li>
				</ul>
				<p class="pa-split__note">
					The one exception is the calendar subscription link, which has to work without a
					login for Outlook and Google to read it. Your account says so plainly, and you can
					replace the link at any time if it has been shared too widely.
				</p>
			</div>

			<div class="pa-split__aside">
				<div class="pa-note">
					<h3>Emails, only where you want them</h3>
					<p>
						We will tell you when a report is ready, when your tier changes and once a month
						with a short summary. Each one is a separate switch, and any of them can be
						pointed at a shared office inbox instead of your own.
					</p>
					<ul>
						<li>New report available</li>
						<li>Tier changes</li>
						<li>Clean request confirmations</li>
						<li>Monthly summary</li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- getting started -->
<section class="section">
	<div class="primary-container">
		<div class="section-head is-center"><h2>Getting started</h2></div>
		<div class="prog-steps">
			<?php
			$steps = array(
				array( '1', 'Join the programme', 'Tell us which branch you are with. A real person replies within one working day.' ),
				array( '2', 'We set your account up', 'Your login, your referral code and your agency price list, ready to use.' ),
				array( '3', 'Sign in and use it', 'Book a clean, collect a report or put your cleans in your own calendar.' ),
			);
			foreach ( $steps as $s ) : ?>
				<div class="prog-step">
					<span class="prog-step__num"><?php echo esc_html( $s[0] ); ?></span>
					<h3><?php echo esc_html( $s[1] ); ?></h3>
					<p><?php echo esc_html( $s[2] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- CTA -->
<section class="section" style="padding-top:0;">
	<div class="primary-container">
		<div class="partners-cta">
			<div>
				<?php if ( $is_partner ) : ?>
					<h2>Your account is ready</h2>
					<p>Everything above is waiting for you now.</p>
				<?php else : ?>
					<h2>Join the bonus program</h2>
					<p>Set-up takes minutes, and your account is live as soon as your branch is approved.</p>
				<?php endif; ?>
			</div>
			<a href="<?php echo esc_url( $is_partner ? $account_url : $register_url ); ?>" class="button button--primary">
				<?php echo $is_partner ? 'Go to your account →' : 'Become a partner →'; ?>
			</a>
		</div>
	</div>
</section>

<?php get_footer(); ?>
