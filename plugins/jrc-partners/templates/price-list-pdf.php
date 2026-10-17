<?php
/**
 * Price list PDF (rendered by dompdf in jrc_price_list_pdf()).
 *
 * dompdf is a CSS 2.1 engine: no flexbox, no grid, no CSS variables. Layout is tables
 * and floats on purpose. Colours are the theme's brand tokens (app/scss/_vars.scss).
 *
 * Expects: $list (jrc_price_list_for_user()), $logo (data URI), $fonts (dir path).
 *
 * @package jrc-partners
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$pct       = (float) $list['percent'];
$pct_label = rtrim( rtrim( number_format( $pct, 2 ), '0' ), '.' ) . '%';
$show_was  = $pct > 0 || $list['has_agreed'];
$e         = 'esc_html';

$row = function ( $it ) use ( $show_was ) {
	$changed = null !== $it['standard'] && null !== $it['price'] && abs( $it['standard'] - $it['price'] ) > 0.001;
	echo '<tr><td class="item"><span class="name">' . esc_html( $it['name'] ) . '</span>';
	if ( $it['note'] ) {
		echo '<br><span class="detail">' . esc_html( $it['note'] ) . '</span>';
	}
	echo '</td>';
	if ( $show_was ) {
		echo '<td class="was">' . ( $changed ? esc_html( jrc_pl_format( $it['standard'], $it['from'], $it['unit'] ) ) : '' ) . '</td>';
	}
	echo '<td class="price">' . esc_html( jrc_pl_format( $it['price'], $it['from'], $it['unit'] ) ) . '</td></tr>';
};
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo $e( $list['title'] . ( $list['account'] ? ' — ' . $list['account'] : '' ) ); ?></title>
<style>
	@font-face { font-family: 'Plus Jakarta Sans'; font-weight: 700; src: url('<?php echo esc_attr( $fonts ); ?>plus-jakarta-sans-700.ttf') format('truetype'); }
	@font-face { font-family: 'Plus Jakarta Sans'; font-weight: 800; src: url('<?php echo esc_attr( $fonts ); ?>plus-jakarta-sans-800.ttf') format('truetype'); }
	@font-face { font-family: 'Hanken Grotesk'; font-weight: 400; src: url('<?php echo esc_attr( $fonts ); ?>hanken-grotesk-400.ttf') format('truetype'); }
	@font-face { font-family: 'Hanken Grotesk'; font-weight: 600; src: url('<?php echo esc_attr( $fonts ); ?>hanken-grotesk-600.ttf') format('truetype'); }

	@page { margin: 0 0 48px 0; }
	body { margin: 0; font-family: 'Hanken Grotesk', sans-serif; font-size: 10.5px; color: #3a3f57; line-height: 1.45; }

	.band { background: #143458; color: #fff; padding: 30px 44px 26px; }
	.band table { width: 100%; border-collapse: collapse; }
	.band td { vertical-align: middle; padding: 0; }
	.band .logo { height: 46px; }
	.band .right { text-align: right; }
	.band h1 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 26px; margin: 0; letter-spacing: -0.3px; }
	.band .date { color: #b6ebe8; font-size: 10px; margin-top: 2px; }
	.accent-bar { height: 5px; background: #13b5b1; }

	.meta { padding: 20px 44px 4px; }
	.meta table { width: 100%; border-collapse: collapse; }
	.meta td { vertical-align: top; padding: 0; }
	.meta .for { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #6b7088; font-weight: 600; }
	.meta .account { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 17px; color: #0b1b2e; margin-top: 2px; }
	.meta .note { color: #54596f; margin-top: 4px; }
	.badge { display: inline-block; background: #eafaf9; border: 1px solid #b6ebe8; color: #06403f; border-radius: 6px; padding: 8px 12px; text-align: center; }
	.badge strong { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 18px; color: #0d8380; display: block; }
	.badge span { font-size: 9px; text-transform: uppercase; letter-spacing: .8px; }

	.content { padding: 6px 44px 0; }
	.intro { color: #54596f; margin: 10px 0 4px; }

	h2 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: 13px; color: #143458; margin: 18px 0 6px; padding-bottom: 5px; border-bottom: 2px solid #13b5b1; }
	table.list { width: 100%; border-collapse: collapse; page-break-inside: auto; }
	table.list tr { page-break-inside: avoid; }
	table.list th { text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: .8px; color: #6b7088; font-weight: 600; padding: 4px 8px; border-bottom: 1px solid #e9ebf0; }
	table.list td { padding: 6px 8px; border-bottom: 1px solid #eef0f4; vertical-align: top; }
	table.list tr:nth-child(even) td { background: #f7f8fa; }
	table.list .name { font-weight: 600; color: #0b1b2e; }
	table.list .detail, .small { color: #6b7088; font-size: 9px; }
	table.list .price, table.list th.price { text-align: right; white-space: nowrap; width: 110px; }
	table.list td.price { font-weight: 600; color: #0b1b2e; }
	table.list .was, table.list th.was { text-align: right; white-space: nowrap; width: 90px; color: #999999; }
	table.list td.was { text-decoration: line-through; }
	table.guide .price { width: 130px; }

	.section { page-break-inside: avoid; }
	.terms { margin: 22px 0 0; padding: 12px 14px; background: #f7f8fa; border-left: 3px solid #13b5b1; color: #54596f; font-size: 9px; }
	.terms ul { margin: 0; padding-left: 14px; }
	.terms li { margin: 0 0 3px; }

	.footer { position: fixed; bottom: -32px; left: 44px; right: 44px; height: 20px; font-size: 8px; color: #6b7088; border-top: 1px solid #e9ebf0; padding-top: 6px; }
</style>
</head>
<body>

<div class="footer">
	J R Cleaning<?php echo $list['contact'] ? ' &nbsp;·&nbsp; ' . $e( $list['contact'] ) : ''; ?>
</div>

<div class="band">
	<table><tr>
		<td><?php if ( $logo ) : ?><img class="logo" src="<?php echo esc_attr( $logo ); ?>" alt="J R Cleaning"><?php else : ?><h1>J R Cleaning</h1><?php endif; ?></td>
		<td class="right">
			<h1><?php echo $e( $list['title'] ); ?></h1>
			<div class="date"><?php echo $e( $list['date'] ); ?></div>
		</td>
	</tr></table>
</div>
<div class="accent-bar"></div>

<div class="meta">
	<table><tr>
		<td>
			<?php if ( $list['account'] ) : ?>
				<div class="for">Prepared for</div>
				<div class="account"><?php echo $e( $list['account'] ); ?></div>
			<?php endif; ?>
			<?php if ( $list['note'] ) : ?><div class="note"><?php echo $e( $list['note'] ); ?></div><?php endif; ?>
		</td>
		<?php if ( $pct > 0 ) : ?>
			<td style="width:130px;text-align:right">
				<div class="badge"><strong><?php echo $e( $pct_label ); ?> off</strong><span>Your agreed rate</span></div>
			</td>
		<?php endif; ?>
	</tr></table>
</div>

<div class="content">
	<?php if ( $list['intro'] ) : ?><p class="intro"><?php echo nl2br( $e( $list['intro'] ) ); ?></p><?php endif; ?>

	<?php foreach ( $list['sections'] as $sec ) : ?>
		<div class="section">
			<h2><?php echo $e( $sec['title'] ); ?></h2>
			<table class="list">
				<thead><tr><th>Service</th><?php if ( $show_was ) : ?><th class="was">Standard</th><?php endif; ?><th class="price">Price</th></tr></thead>
				<tbody><?php foreach ( $sec['items'] as $it ) { $row( $it ); } ?></tbody>
			</table>
		</div>
	<?php endforeach; ?>

	<?php if ( $list['guide'] ) : ?>
		<div class="section">
			<h2><?php echo $e( $list['guide_title'] ); ?></h2>
			<table class="list guide">
				<thead><tr><th>Property size</th><th class="price">End of tenancy</th><th class="price">Sparkle clean</th></tr></thead>
				<tbody>
					<?php foreach ( $list['guide'] as $g ) : ?>
						<tr>
							<td><span class="name"><?php echo $e( $g['size'] ); ?></span></td>
							<td class="price"><?php echo $e( jrc_pl_format( $g['eot'] ) ); ?></td>
							<td class="price"><?php echo $e( jrc_pl_format( $g['sparkle'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $list['guide_note'] ) : ?><p class="small"><?php echo $e( $list['guide_note'] ); ?></p><?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $list['addons'] ) : ?>
		<div class="section">
			<h2>Add-ons</h2>
			<table class="list">
				<thead><tr><th>Add-on</th><?php if ( $show_was ) : ?><th class="was">Standard</th><?php endif; ?><th class="price">Price</th></tr></thead>
				<tbody><?php foreach ( $list['addons'] as $it ) { $row( $it ); } ?></tbody>
			</table>
		</div>
	<?php endif; ?>

	<?php if ( $list['footer'] ) : ?>
		<div class="terms"><ul><?php foreach ( $list['footer'] as $line ) : ?><li><?php echo $e( $line ); ?></li><?php endforeach; ?></ul></div>
	<?php endif; ?>
</div>

</body>
</html>
