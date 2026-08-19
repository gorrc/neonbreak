<?php get_header(); ?>
<section class="hero">
  <div class="nb-wrap hero-content">
    <p class="eyebrow">Independent AI & web engineering studio</p>
    <h1>We build web applications and <span class="hero-accent">practical AI systems.</span></h1>
    <p class="hero-copy">We use AI twice: as an accelerator for better software development, and as a practical technology inside the products we build. The result is leaner, faster and distinctly yours.</p>
    <div class="button-row">
      <a class="button button-primary" href="#work">Explore our approach</a>
      <a class="button" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Tell us what you’re building →</a>
    </div>
    <div class="hero-meta" aria-label="Capabilities">
      <span><i></i> AI-native development</span><span><i></i> Custom web applications</span><span><i></i> Based in Croatia · Working globally</span>
    </div>
  </div>
</section>

<section class="section" id="work">
  <div class="nb-wrap">
    <div class="section-head capability-head">
      <div><p class="eyebrow">What we do</p><h2>Web applications, AI and cloud infrastructure.</h2></div>
    </div>
    <div class="capability-grid">
      <article class="capability-card"><span class="card-index">01 / WEB APPLICATIONS</span><h3>PHP, Node.js and Firebase</h3><p>Custom applications in PHP or Node.js. For suitable products, Firebase provides authentication, data, storage and hosting in one Google Cloud-connected stack.</p></article>
      <article class="capability-card"><span class="card-index">02 / AI SYSTEMS</span><h3>Managed or custom AI</h3><p>Google and AWS services for managed implementations, or custom model pipelines through providers such as Groq and Fireworks when latency, model choice or inference cost needs tighter control.</p></article>
      <article class="capability-card"><span class="card-index">03 / CLOUD</span><h3>AWS and Google Cloud</h3><p>Applications deployed on the cloud platform that fits their architecture, including compute, storage, databases, networking, monitoring and production releases.</p></article>
    </div>
  </div>
</section>

<section class="section build-process">
  <div class="nb-wrap">
    <div class="section-head process-head">
      <div><p class="eyebrow">How we build</p><h2>AI-assisted development, organised as engineering work.</h2></div>
    </div>
    <div class="process-grid">
      <article class="process-step">
        <span class="card-index">01 / PROTOTYPE</span>
        <h3>Test the product shape</h3>
        <p>We use AI to build an early working prototype and expose unclear requirements before they become expensive implementation decisions.</p>
      </article>
      <article class="process-step">
        <span class="card-index">02 / MODULARISE</span>
        <h3>Split the system into modules</h3>
        <p>The application is divided into bounded parts with explicit interfaces, acceptance criteria and dependencies.</p>
      </article>
      <article class="process-step">
        <span class="card-index">03 / BUILD</span>
        <h3>Orchestrate focused agents</h3>
        <p>Claude, Antigravity, Codex and Copilot are used for constrained development tasks. Work is delivered in small, reviewable iterations toward the MVP.</p>
      </article>
      <article class="process-step">
        <span class="card-index">04 / VERIFY</span>
        <h3>Cross-check important decisions</h3>
        <p>Critical architecture, security and data decisions are challenged across multiple tools, tested against the code and approved by a developer.</p>
      </article>
    </div>
  </div>
</section>

<section class="section proof" id="why-custom">
  <div class="nb-wrap">
    <div class="section-head">
      <div><p class="eyebrow">Why custom</p><h2>Own the solution, not just a subscription.</h2></div>
      <p class="section-intro">A custom application can follow an existing workflow, connect the systems already in use and avoid paying for unrelated features. It is not always the better option; the decision depends on scope, maintenance and long-term cost.</p>
    </div>
    <div class="comparison-grid">
      <article class="comparison"><span class="comparison-label">Example 01 · Model infrastructure</span><h3>Pay for inference only when it is used.</h3><p>An application can call an on-demand model when a request arrives instead of paying for dedicated model capacity that remains reserved during periods with little or no traffic.</p><ul class="comparison-list"><li>No continuously running GPU or model server for an early product</li><li>Usage-based cost while traffic is low or irregular</li><li>Move to dedicated capacity when sustained volume makes it more economical</li></ul><p class="fine-print">Dedicated inference can provide more predictable latency, capacity and isolation. The cost comparison depends on traffic volume and the selected model.</p></article>
      <article class="comparison"><span class="comparison-label">Example 02 · Application knowledge</span><h3>Use RAG when the facts change.</h3><p>Retrieval-augmented generation finds relevant content at request time and supplies it to the model. Documents can be updated without retraining the model, while the application controls access and can show the sources used for an answer.</p><ul class="comparison-list"><li>Update the knowledge base independently from the model</li><li>Filter retrieval by user, organisation or document permissions</li><li>Use fine-tuning for behaviour or format—not as the default way to store changing facts</li></ul><p class="fine-print">RAG still requires good document processing, retrieval tests and rules for answers that are not supported by a source.</p></article>
    </div>
  </div>
</section>

<section class="section decision-section" id="decisions">
  <div class="nb-wrap">
    <div class="section-head decision-head">
      <div><p class="eyebrow">Technology decisions, explained</p><h2>What we use, where it fits and where it doesn’t.</h2></div>
    </div>
    <article class="decision-card">
      <header class="decision-title">
        <div class="tech-symbol tech-symbol-code" aria-hidden="true">{ }</div>
        <div><span class="decision-kicker">01 · Application runtime</span><h3>PHP and Node.js</h3></div>
      </header>
      <div class="decision-columns">
        <section><span class="decision-status good">PHP fits</span><h4>Content, administration and transactions</h4><p>PHP is a practical choice for server-rendered sites, WordPress, customer portals and business applications built around forms, records and relational data.</p><ul><li>Mature hosting and framework ecosystem</li><li>Direct fit for WordPress development</li><li>Clear request-and-response application model</li></ul></section>
        <section><span class="decision-status good">Node.js fits</span><h4>Real-time and event-driven applications</h4><p>Node.js is useful when the application handles live updates, queues, streaming APIs or benefits from sharing JavaScript types and packages between frontend and backend.</p><ul><li>Non-blocking I/O for concurrent connections</li><li>Strong JavaScript and TypeScript ecosystem</li><li>Natural fit for API and integration services</li></ul></section>
      </div>
      <footer class="decision-footer"><p><strong>They are not mutually exclusive:</strong> a project can use PHP for its core business application and Node.js for a clearly separated real-time or background-processing service.</p></footer>
    </article>
    <article class="decision-card">
      <header class="decision-title">
        <div class="tech-symbol" aria-hidden="true">F</div>
        <div><span class="decision-kicker">02 · Managed application platform</span><h3>Firebase</h3></div>
      </header>
      <div class="decision-columns">
        <section>
          <span class="decision-status good">A good fit</span>
          <h4>Products driven by users and real-time activity</h4>
          <p>Marketplaces, social feeds, messaging, booking flows and startup MVPs can benefit from managed authentication, document data, storage and real-time updates.</p>
          <ul><li>Usage-based infrastructure with no-cost allowances</li><li>No server fleet to manage during early validation</li><li>A clear path from prototype to high traffic</li></ul>
        </section>
        <section>
          <span class="decision-status caution">Not our default</span>
          <h4>Systems built around complex relational data</h4>
          <p>Accounting software, inventory-heavy commerce and reporting systems often need transactions, joins and relational constraints at the centre of the design.</p>
          <ul><li>Read patterns can make document billing unpredictable</li><li>Denormalised models add application complexity</li><li>PostgreSQL or a hybrid architecture may be clearer</li></ul>
        </section>
      </div>
      <footer class="decision-footer">
        <p><strong>The important distinction:</strong> Cloud Firestore is a NoSQL document database. Firebase also offers a managed PostgreSQL path, so we choose the data layer—not just the brand name.</p>
        <a href="<?php echo esc_url( home_url( '/services/firebase/' ) ); ?>">How we work with Firebase →</a>
      </footer>
    </article>
    <article class="decision-card">
      <header class="decision-title">
        <div class="tech-symbol tech-symbol-ai" aria-hidden="true">AI</div>
        <div><span class="decision-kicker">03 · AI infrastructure</span><h3>Managed AI or specialised inference</h3></div>
      </header>
      <div class="decision-columns">
        <section><span class="decision-status good">Google / AWS</span><h4>Integrated enterprise AI platforms</h4><p>Google Cloud and AWS combine models with identity, storage, monitoring, governance and other cloud services. That integration is useful when those controls are part of the requirement.</p><ul><li>Centralised access and cloud governance</li><li>Integration with existing cloud data</li><li>Broad managed-service ecosystem</li></ul></section>
        <section><span class="decision-status good">Groq / Fireworks</span><h4>Focused model inference APIs</h4><p>Specialised providers can offer a smaller integration surface and additional choices for open models, latency and inference pricing when a complete enterprise platform is unnecessary.</p><ul><li>Direct, API-focused implementation</li><li>Choice of supported open models</li><li>Costs measured against the actual workload</li></ul></section>
      </div>
      <footer class="decision-footer"><p><strong>The model is only one component:</strong> retrieval, permissions, evaluation, logging and fallback behaviour still need to be designed in the application.</p></footer>
    </article>
    <article class="decision-card">
      <header class="decision-title">
        <div class="tech-symbol tech-symbol-cloud" aria-hidden="true">☁</div>
        <div><span class="decision-kicker">04 · Hosting infrastructure</span><h3>AWS and Google Cloud</h3></div>
      </header>
      <div class="decision-columns">
        <section><span class="decision-status good">Cloud fits</span><h4>Applications with changing requirements</h4><p>Cloud platforms provide managed databases, object storage, queues, private networks, monitoring and capacity that can be changed without replacing the entire hosting environment.</p><ul><li>Scale individual components independently</li><li>Managed backups, regions and access controls</li><li>Infrastructure can be described and reproduced</li></ul></section>
        <section><span class="decision-status caution">Classic hosting fits</span><h4>Small and predictable workloads</h4><p>A conventional server or hosting plan can be simpler and cheaper for a stable website or application that does not need several managed services or rapid changes in capacity.</p><ul><li>Predictable monthly cost</li><li>Less infrastructure to configure</li><li>A better fit when scaling needs are modest</li></ul></section>
      </div>
      <footer class="decision-footer"><p><strong>Cloud is not automatically cheaper:</strong> its advantage is service choice, automation and scaling. Costs still require monitoring and architecture limits.</p></footer>
    </article>
  </div>
</section>

<section class="section">
  <div class="nb-wrap cta-panel">
    <div><p class="eyebrow">Start a conversation</p><h2>Have a process that should work better?</h2></div>
    <div class="cta-actions"><a class="button button-primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Let’s map the opportunity</a></div>
  </div>
</section>
<?php get_footer(); ?>
