<?php
/**
 * Partner tab: the account's own price list, with a PDF download.
 *
 * Renders from jrc_price_list_for_user() — the same figures the PDF is built from — so
 * the screen and the download can never disagree.
 *
 * @package jrc-partners
 */
if ( ! defined( 'ABSPATH' ) || empty( $aff ) ) { return; }

$list = jrc_price_list_for_user();
$pct  = (float) $list['percent'];
$pct_label = rtrim( rtrim( number_format( $pct, 2 ), '0' ), '.' ) . '%';
?>
<div class="aff-pl">

	<?php if ( jrc_pl_is_empty( $list ) ) : ?>
		<div class="aff-card aff-card--note">
			<p>Your price list is being prepared. Once it's ready it will appear here, with a PDF you can send on to your own customers.</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<div class="aff-pl__head aff-card">
		<div>
			<span class="aff-card__eyebrow"><?php echo esc_html( $list['title'] ); ?></span>
			<h3 class="aff-pl__account"><?php echo esc_html( $list['account'] ? 'Prepared for ' . $list['account'] : 'Your price list' ); ?></h3>
			<p class="aff-pl__rate">
				<?php if ( $pct > 0 ) : ?>
					Your agreed rate of <strong><?php echo esc_html( $pct_label ); ?> off</strong> is already applied<?php echo $list['has_agreed'] ? ', along with prices agreed individually for your account' : ''; ?>.
				<?php elseif ( $list['has_agreed'] ) : ?>
					Includes prices agreed individually for your account.
				<?php else : ?>
					Our standard prices.
				<?php endif; ?>
			</p>
			<?php if ( $list['note'] ) : ?>
				<p class="aff-pl__note"><?php echo esc_html( $list['note'] ); ?></p>
			<?php endif; ?>
		</div>
		<div class="aff-pl__actions">
			<a class="button button--primary" href="<?php echo esc_url( jrc_price_list_pdf_url() ); ?>">Download PDF</a>
			<span class="aff-pl__date">Current as of <?php echo esc_html( $list['date'] ); ?></span>
		</div>
	</div>

	<?php if ( $list['intro'] ) : ?>
		<p class="aff-pl__intro"><?php echo nl2br( esc_html( $list['intro'] ) ); ?></p>
	<?php endif; ?>

	<?php
	$show_was = $pct > 0 || $list['has_agreed'];
	$row = function ( $it ) use ( $show_was ) {
		$changed = null !== $it['standard'] && null !== $it['price'] && abs( $it['standard'] - $it['price'] ) > 0.001;
		?>
		<tr>
			<td>
				<span class="aff-pl__item"><?php echo esc_html( $it['name'] ); ?></span>
				<?php if ( $it['note'] ) : ?><span class="aff-pl__detail"><?php echo esc_html( $it['note'] ); ?></span><?php endif; ?>
			</td>
			<?php if ( $show_was ) : ?>
				<td class="aff-pl__was"><?php echo $changed ? esc_html( jrc_pl_format( $it['standard'], $it['from'], $it['unit'] ) ) : ''; ?></td>
			<?php endif; ?>
			<td class="aff-pl__price"><?php echo esc_html( jrc_pl_format( $it['price'], $it['from'], $it['unit'] ) ); ?></td>
		</tr>
		<?php
	};
	?>

	<?php foreach ( $list['sections'] as $sec ) : ?>
		<section class="aff-pl__section">
			<h3><?php echo esc_html( $sec['title'] ); ?></h3>
			<div class="aff-jobs__scroll">
				<table class="aff-pl__table">
					<thead><tr><th>Service</th><?php if ( $show_was ) : ?><th class="aff-pl__was">Standard</th><?php endif; ?><th class="aff-pl__price">Your price</th></tr></thead>
					<tbody><?php foreach ( $sec['items'] as $it ) { $row( $it ); } ?></tbody>
				</table>
			</div>
		</section>
	<?php endforeach; ?>

	<?php if ( $list['guide'] ) : ?>
		<section class="aff-pl__section">
			<h3><?php echo esc_html( $list['guide_title'] ); ?></h3>
			<div class="aff-jobs__scroll">
				<table class="aff-pl__table aff-pl__table--guide">
					<thead><tr><th>Property size</th><th class="aff-pl__price">End of tenancy</th><th class="aff-pl__price">Sparkle clean</th></tr></thead>
					<tbody>
						<?php foreach ( $list['guide'] as $g ) : ?>
							<tr>
								<td><span class="aff-pl__item"><?php echo esc_html( $g['size'] ); ?></span></td>
								<td class="aff-pl__price"><?php echo esc_html( jrc_pl_format( $g['eot'] ) ); ?></td>
								<td class="aff-pl__price"><?php echo esc_html( jrc_pl_format( $g['sparkle'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php if ( $list['guide_note'] ) : ?><p class="aff-pl__detail"><?php echo esc_html( $list['guide_note'] ); ?></p><?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( $list['addons'] ) : ?>
		<section class="aff-pl__section">
			<h3>Add-ons</h3>
			<div class="aff-jobs__scroll">
				<table class="aff-pl__table">
					<thead><tr><th>Add-on</th><?php if ( $show_was ) : ?><th class="aff-pl__was">Standard</th><?php endif; ?><th class="aff-pl__price">Your price</th></tr></thead>
					<tbody><?php foreach ( $list['addons'] as $it ) { $row( $it ); } ?></tbody>
				</table>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $list['footer'] ) : ?>
		<ul class="aff-pl__terms">
			<?php foreach ( $list['footer'] as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<p class="aff-dash__foot">This price list is for reference — to book, use <a href="<?php echo esc_url( jrc_affiliate_tab_url( 'request-clean' ) ); ?>">Request a clean</a>. Questions about your rates? <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Get in touch</a>.</p>
</div>
