<?php get_header(); while ( have_posts() ) : the_post(); $slug = get_post_field( 'post_name' ); ?>
<section class="content-page legal-page"><div class="nb-wrap"><p class="eyebrow">Legal</p><h1><?php the_title(); ?></h1><p class="legal-updated">Last updated: <?php echo esc_html( wp_date( 'F j, Y' ) ); ?></p><article class="legal-copy">
<?php if ( 'privacy-policy' === $slug ) : ?>
  <h2>1. Who we are</h2><p>Neon Break is a brand operated by Web Solutions, obrt za informatičke usluge, vl. Goran Sambolić, with its registered office at Varaždinska ulica 11, Zlatar, Croatia. The business is registered in the Croatian Craft Register under MBO 99052687. VAT ID: HR48785849869. Contact: <a href="mailto:info@neonbreak.com">info@neonbreak.com</a>.</p>
  <h2>2. Information we collect</h2><p>We may collect contact details and information you choose to send us, along with limited technical logs needed to keep this website secure and operational.</p>
  <h2>3. Why we use it</h2><p>We process information to respond to enquiries, provide contracted services, meet legal obligations and protect our systems. The legal basis may be consent, contract, legal obligation or legitimate interest.</p>
  <h2>4. Sharing and retention</h2><p>We share data with service providers only when needed to operate our business and under appropriate safeguards. We retain it no longer than required for its purpose and applicable law.</p>
  <h2>5. Your rights</h2><p>Under the GDPR, you may request access, correction, deletion, restriction or portability, or object to certain processing. You may also complain to the Croatian Personal Data Protection Agency.</p>
  <h2>6. Contact</h2><p>Send privacy requests to <a href="mailto:info@neonbreak.com">info@neonbreak.com</a>.</p>
<?php elseif ( 'cookie-policy' === $slug ) : ?>
  <h2>1. About cookies</h2><p>Cookies are small files stored by your browser. This website is designed to use only essential cookies unless optional services are added later.</p>
  <h2>2. Essential cookies</h2><p>WordPress may use cookies for authentication, security and user preferences. These are required when you sign in to restricted areas.</p>
  <h2>3. Optional cookies</h2><p>We do not intentionally set analytics or advertising cookies in this release. If this changes, we will update this policy and request consent where required.</p>
  <h2>4. Browser controls</h2><p>You can remove or block cookies in your browser settings. Blocking essential cookies may prevent restricted functionality from working.</p>
  <h2>5. Contact</h2><p>Questions can be sent to <a href="mailto:info@neonbreak.com">info@neonbreak.com</a>.</p>
<?php else : ?>
  <h2>1. Scope</h2><p>These terms govern use of this website. Separate written terms apply to paid services and client deliverables.</p>
  <h2>2. Website information</h2><p>Website content is provided for general information and does not constitute professional, legal or financial advice.</p>
  <h2>3. Intellectual property</h2><p>Unless stated otherwise, original website text, design and materials belong to Web Solutions, obrt za informatičke usluge, vl. Goran Sambolić, operating under the Neon Break brand, and may not be reproduced commercially without permission.</p>
  <h2>4. Acceptable use</h2><p>Do not misuse the website, attempt unauthorised access, interfere with its availability or submit unlawful or harmful material.</p>
  <h2>5. Liability and law</h2><p>To the extent permitted by law, we are not liable for indirect loss arising solely from use of this public website. These terms are governed by Croatian law without limiting mandatory consumer rights.</p>
  <h2>6. Business identification</h2><p>Web Solutions, obrt za informatičke usluge, vl. Goran Sambolić. Registered office: Varaždinska ulica 11, Zlatar, Croatia. Croatian Craft Register MBO: 99052687. VAT ID: HR48785849869. Contact: <a href="mailto:info@neonbreak.com">info@neonbreak.com</a>.</p>
<?php endif; ?>
<div class="legal-notice"><strong>Pre-launch review required</strong><p>Final information about hosting, processors, analytics and the commercial model must be confirmed before publication.</p></div></article></div></section>
<?php endwhile; get_footer(); ?>
