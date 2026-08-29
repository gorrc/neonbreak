<?php get_header(); ?>
<section class="contact-hero"><div class="nb-wrap"><p class="eyebrow">Contact</p><h1>Describe the application or process you want to improve.</h1><p>A few practical details are enough: what needs to work, who will use it and what currently causes the problem.</p></div></section>
<section class="contact-section"><div class="nb-wrap contact-grid">
  <div class="contact-panel"><h2>Project enquiry</h2>
    <?php $contact_status = isset( $_GET['contact'] ) ? sanitize_key( wp_unslash( $_GET['contact'] ) ) : ''; ?>
    <?php if ( 'sent' === $contact_status ) : ?><div class="form-message form-success" role="status">Thank you. Your message has been sent.</div><?php endif; ?>
    <?php if ( 'error' === $contact_status ) : ?><div class="form-message form-error" role="alert">The message could not be sent. Please try again later.</div><?php endif; ?>
    <?php if ( 'invalid' === $contact_status ) : ?><div class="form-message form-error" role="alert">Please check the required fields and try again.</div><?php endif; ?>
    <?php if ( 'rate' === $contact_status ) : ?><div class="form-message form-error" role="alert">Please wait a minute before sending another message.</div><?php endif; ?>
    <form class="contact-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
      <input type="hidden" name="action" value="neonbreak_contact"><?php wp_nonce_field( 'neonbreak_contact', 'neonbreak_contact_nonce' ); ?>
      <div class="honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="field-grid"><label>Name<input name="name" autocomplete="name" required></label><label>Work email<input name="email" type="email" autocomplete="email" required></label></div>
      <label>Company <span>(optional)</span><input name="company" autocomplete="organization"></label>
      <label>What do you need to build or improve?<textarea name="message" rows="7" required placeholder="Current process, intended users, required integrations or a link to an existing product."></textarea></label>
      <button class="button button-primary" type="submit">Send enquiry</button>
      <p class="form-note">The submitted information is used only to reply to this enquiry. See our <a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">Privacy Policy</a>.</p>
    </form>
  </div>
  <aside class="contact-details"><p class="eyebrow">Before sending</p><h2>Useful details</h2><ul><li>What should the application do?</li><li>Who will use it?</li><li>Does it replace an existing process?</li><li>Which systems must it connect to?</li><li>Is there a required launch date?</li></ul><hr><h3>Location</h3><p>Zlatar, Croatia<br>Remote collaboration across Croatia and the EU.</p></aside>
</div></section>
<?php get_footer(); ?>
