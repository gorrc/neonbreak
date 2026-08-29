<?php get_header(); ?>

<section class="nl-publish-hero"><div class="nb-wrap nl-publish-grid">
  <div>
    <p class="eyebrow">Android knowledge library</p>
    <h1>Your knowledge, on your device.<br><span class="hero-accent">Expanded by subscriptions.</span></h1>
    <p class="nl-publish-lead">NeonLib is an Android application that turns your own documents and notes into a private, searchable knowledge base. Add maintained knowledge subscriptions when you want trusted content from publishers, teams or organisations in the same library.</p>
    <div class="button-row"><a class="button button-primary" href="<?php echo esc_url( home_url( '/account/' ) ); ?>">Create a publisher account</a><a class="button" href="#subscriptions">See how the library grows &darr;</a></div>
    <p class="nl-account-note">Want to publish your own knowledge subscription? Create a NeonLib account to organise documents, manage access and release versioned updates to Android users.</p>
  </div>
  <aside class="nl-browser" aria-label="NeonLib Android knowledge library preview">
    <div class="nl-browser-bar"><i></i><i></i><i></i><span>NeonLib · Android</span></div>
    <div class="nl-browser-body"><span class="nl-browser-label">YOUR KNOWLEDGE LIBRARY</span><h2>Search everything you trust</h2><p>Private documents + subscribed knowledge</p><div class="nl-browser-search">Ask your knowledge base <b>&#128269;</b></div><div class="nl-browser-rows"><span><i></i>My private library</span><span><i></i>Product Support · Subscription</span><span><i></i>Team Handbook · Subscription</span></div><strong class="nl-browser-status">AVAILABLE ON ANDROID</strong></div>
  </aside>
</div></section>

<section class="section nl-subscriptions" id="subscriptions"><div class="nb-wrap">
  <div class="section-head"><div><p class="eyebrow">One library, two sources</p><h2>Start with your own knowledge. Add maintained knowledge when you need it.</h2></div><p class="section-intro">Your private library is created from documents you choose. Subscriptions extend it with versioned knowledge packages whose updates are maintained by their publishers.</p></div>
  <div class="nl-role-grid">
    <article><span>01 / YOUR LIBRARY</span><h3>Build a private knowledge base</h3><p>Import your documents and notes into the Android application, then search them by meaning instead of relying only on filenames or keywords.</p><ul><li>Your documents stay on your device</li><li>Local embeddings and vector search</li><li>Optional AI answers use retrieved context</li></ul></article>
    <article class="nl-role-bridge"><span>02 / SUBSCRIPTIONS</span><h3>Add trusted knowledge</h3><p>Subscribe to knowledge maintained by a publisher, company or team and use it alongside your own library from one application.</p><ul><li>Public or access-controlled packages</li><li>Traceable, immutable releases</li><li>Updates without manual re-importing</li></ul></article>
    <article><span>03 / PUBLISHERS</span><h3>Maintain and distribute</h3><p>Publishers build subscriptions in the browser, control their source content and release new versions for connected users.</p><ul><li>Structured document collections</li><li>Stable package identity and permissions</li><li>Controlled delivery to Android users</li></ul></article>
  </div>
</div></section>

<section class="section nl-lifecycle"><div class="nb-wrap">
  <div class="section-head"><div><p class="eyebrow">Publishing workflow</p><h2>From browser editor to a searchable release.</h2></div></div>
  <ol class="nl-steps">
    <li><span>01</span><div><h3>Create a subscription</h3><p>Define the package, language, description and visibility from the NeonLib account dashboard.</p></div></li>
    <li><span>02</span><div><h3>Add the knowledge</h3><p>Build the document collection that subscribers should be able to search and use.</p></div></li>
    <li><span>03</span><div><h3>Publish an immutable version</h3><p>Release a traceable snapshot instead of changing subscriber content silently.</p></div></li>
    <li><span>04</span><div><h3>Share with users</h3><p>Make the knowledge subscription available to the intended audience and continue maintaining future versions.</p></div></li>
  </ol>
</div></section>

<section class="section nl-personal"><div class="nb-wrap nl-personal-grid">
  <div><p class="eyebrow">Local-first foundation</p><h2>The Android application is your knowledge layer.</h2></div>
  <div><p>NeonLib processes documents into embeddings and stores them with the vector index on the Android device. Relevant passages are retrieved locally. If online answering is enabled, only the question and selected context are sent to the configured AI provider—not the complete library. Subscriptions use the same searchable experience while adding maintained, versioned sources.</p><div class="project-stack"><span>Android</span><span>EmbeddingGemma 300M</span><span>LiteRT</span><span>ObjectBox HNSW</span><span>Local RAG</span><span>Versioned subscriptions</span><span>Optional online AI</span></div><a class="service-link" href="<?php echo esc_url( home_url( '/services/applied-ai/#neonlib' ) ); ?>">Read the technical Android case study &rarr;</a></div>
</div></section>

<section class="section nl-final"><div class="nb-wrap"><div class="cta-panel"><div><p class="eyebrow">Publish your knowledge</p><h2>Create an account to build, manage and release your own subscriptions.</h2><p class="nl-account-note">The publisher account is for subscription creation and access management. Your private knowledge library remains in the Android application.</p></div><div class="cta-actions"><a class="button button-primary" href="<?php echo esc_url( home_url( '/account/' ) ); ?>">Create a publisher account &rarr;</a></div></div></div></section>

<?php get_footer(); ?>
