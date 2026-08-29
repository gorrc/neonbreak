<?php
/* Template Name: Neon Break Service Detail */
$service_key = get_post_field( 'post_name', get_queried_object_id() );
$services = array(
    'php-nodejs' => array(
        'index' => '01 / APPLICATION DEVELOPMENT',
        'title' => 'PHP & Node.js',
        'lead' => 'Two established server-side platforms selected according to the application workload, existing systems and maintenance requirements.',
        'summary' => 'We use PHP for WordPress, Magento, content-heavy products, administration and transactional workflows. CakePHP is our preferred framework for custom PHP applications when rapid application development fits the project. Laravel is supported when its ecosystem, an existing codebase or the client team makes it the more practical choice. We use React for application interfaces, Next.js when server rendering and an integrated full-stack structure are useful, and Node.js for APIs, integrations, background processing and real-time features.',
        'good_title' => 'Where each runtime fits',
        'good' => array( 'WordPress for content-driven websites and platforms', 'Magento for established e-commerce requirements', 'CakePHP for rapid development of custom business applications', 'Laravel when package availability or team familiarity matters', 'React for application interfaces', 'Next.js for full-stack and server-rendered web applications', 'Node.js and TypeScript for APIs, integrations, queues and real-time services' ),
        'limits_title' => 'What we decide before implementation',
        'limits' => array( 'Whether one runtime is sufficient', 'Where a service boundary provides a real benefit', 'Database and transaction requirements', 'Hosting, monitoring and long-term maintenance' ),
        'note' => 'CakePHP is our preferred starting point for custom PHP work because its conventions, code generation and built-in application structure reduce repetitive setup. Laravel is the alternative when its larger ecosystem, available packages or the project team provide a concrete advantage. PHP and Node.js can also be combined when each runtime has a clear responsibility.',
    ),
    'firebase' => array(
        'index' => '02 / GOOGLE CLOUD APPLICATION STACK',
        'title' => 'Firebase & Google Cloud',
        'lead' => 'A practical route from an AI product interface to managed identity, data, automation and Google Maps services inside one Google infrastructure.',
        'summary' => 'We use Firebase as the application layer of Google Cloud: the product can start with managed services, connect directly to Google APIs and grow without introducing a separate infrastructure stack for every new capability.',
        'good_title' => 'What integrates cleanly',
        'good' => array( 'Firebase identity and security rules across web and mobile clients', 'Real-time product data, files and server-side workflows', 'Google Maps Platform for maps, places, geocoding and routes', 'Gemini-powered actions connected through protected backend functions' ),
        'limits_title' => 'What we design up front',
        'limits' => array( 'Firestore document structure, indexes and read patterns', 'API-key restrictions, IAM roles and separation of client/server credentials', 'Quotas, billing alerts and AI or Maps usage limits', 'Regional placement, observability and a path to other Google Cloud services' ),
        'note' => 'Firebase projects are Google Cloud projects. This lets the application share billing, IAM, service accounts, monitoring and API governance with Google Maps Platform, Gemini and the wider Google Cloud ecosystem.',
        'case_study' => array(
            'label' => 'Case study · Natdian.com',
            'title' => 'An AI map assistant on Google infrastructure',
            'intro' => 'Natdian is a complete AI map assistant built as a Next.js application. A user can describe an intent in natural language and turn it into routes, places, events, reminders and other map-related actions. The key architectural decision was to keep identity, application data, AI orchestration and Google Maps capabilities inside one connected Google stack.',
            'requirements_label' => 'AI ASSISTANT WORKFLOWS',
            'requirements' => array( 'Turn natural-language requests into structured map actions', 'Create and update routes, places, events and reminders', 'Keep personal maps and assistant context synchronized in real time', 'Run privileged AI and data operations outside the browser' ),
            'implementation_label' => 'FIREBASE SERVICES',
            'implementation' => array( 'Firebase Authentication for user identity and sessions', 'Cloud Firestore for maps, places, routes, events, profiles and assistant state', 'Cloud Storage for user-generated files and media', 'Cloud Functions for protected AI orchestration, triggers and scheduled actions', 'Firebase App Hosting for the Next.js application and deployment pipeline', 'Firebase Local Emulator Suite for local development and security-rule testing' ),
            'google_label' => 'GOOGLE APIs & INFRASTRUCTURE',
            'google_services' => array( 'Google Maps JavaScript API for the interactive map experience', 'Places API for place discovery, details and autocomplete', 'Routes API for route calculation, waypoints and travel data', 'Geocoding API for converting addresses and coordinates', 'Gemini API for intent understanding and structured assistant actions', 'Google Cloud IAM, service accounts, billing, quotas and Cloud Logging as the shared operational layer' ),
            'result' => 'Firebase handles the product state while Google APIs provide geographic and AI capabilities. Because everything belongs to the same Google Cloud project boundary, credentials, permissions, quotas, logs and costs can be governed centrally. New assistant actions can be added as protected functions without rebuilding the authentication, deployment or data infrastructure.',
            'link_url' => 'https://natdian.com/',
            'link_label' => 'Visit Natdian.com →',
        ),
    ),
    'applied-ai' => array(
        'index' => '03 / AI IMPLEMENTATION',
        'title' => 'Applied AI',
        'lead' => 'Search, extraction, assistants and automation implemented around a defined task, data source and measurable expected result.',
        'summary' => 'Google and AWS offer broad managed AI platforms. Groq, Fireworks and other focused inference providers can provide a smaller integration surface when the application does not need a complete enterprise platform.',
        'good_title' => 'Managed platforms',
        'good' => array( 'Integrated identity, storage and cloud governance', 'Existing workloads already running in the same cloud', 'Organisation-wide model and access controls', 'Requirements that justify a broader managed ecosystem' ),
        'limits_title' => 'Focused model APIs',
        'limits' => array( 'Direct inference integration for a defined feature', 'More control over supported models and providers', 'Latency and token cost tested against real usage', 'Less platform surface to configure and maintain' ),
        'note' => 'The model is one component. Retrieval, permissions, evaluation, logging and fallback behaviour remain application work.',
        'case_study' => array(
            'label' => 'Case study · NeonLib for Android',
            'title' => 'Private on-device retrieval with online AI answers',
            'intro' => 'NeonLib turns documents and notes into a searchable knowledge library on an Android device. Retrieval is performed locally, while the user can optionally send only the question and the most relevant retrieved passages to a selected online AI provider.',
            'requirements' => array( 'Import documents and organise notes into collections', 'Search by meaning instead of exact keywords', 'Keep the full knowledge library and vector index on the device', 'Allow the user to choose whether an online model receives retrieved context' ),
            'implementation' => array( 'EmbeddingGemma 300M running locally through LiteRT', '768-dimensional embeddings stored in an ObjectBox HNSW vector index', 'Local chunking, semantic retrieval and collection-level filtering', 'RAG prompt assembled only from the highest-ranking passages', 'OpenAI, Gemini and Groq provider support', 'Encrypted storage for the user-provided API configuration' ),
            'result' => 'Raw documents and the complete vector index remain on the Android device. When online answering is enabled, the application sends the query and selected supporting passages—not the entire knowledge library—to the configured model provider.',
        ),
    ),
    'cloud-infrastructure' => array(
        'index' => '04 / INFRASTRUCTURE',
        'title' => 'Cloud Infrastructure',
        'lead' => 'AWS or Google Cloud infrastructure assembled from the services the application actually needs.',
        'summary' => 'Cloud hosting allows compute, storage, databases and networking to scale independently. Conventional hosting can remain the simpler and cheaper choice for small, predictable workloads.',
        'good_title' => 'Why use cloud infrastructure',
        'good' => array( 'Managed databases, queues and object storage', 'Independent scaling of application components', 'Reproducible infrastructure and automated deployment', 'Monitoring, access control, backups and regional options' ),
        'limits_title' => 'When classic hosting may be better',
        'limits' => array( 'Stable website or application traffic', 'No requirement for several managed services', 'Predictable monthly infrastructure budget', 'A single server is sufficient for the workload' ),
        'note' => 'Cloud is not automatically cheaper. Its advantages are flexibility, managed services and controlled scaling.',
    ),
);
$service = isset( $services[ $service_key ] ) ? $services[ $service_key ] : null;
if ( ! $service ) { get_template_part( 'page' ); return; }
get_header();
?>
<section class="service-detail-hero"><div class="nb-wrap"><p class="eyebrow"><?php echo esc_html( $service['index'] ); ?></p><h1><?php echo esc_html( $service['title'] ); ?></h1><p><?php echo esc_html( $service['lead'] ); ?></p></div></section>
<section class="service-detail-body"><div class="nb-wrap">
  <div class="service-summary"><p><?php echo esc_html( $service['summary'] ); ?></p></div>
  <div class="service-detail-grid">
    <article><span class="decision-status good">Use case</span><h2><?php echo esc_html( $service['good_title'] ); ?></h2><ul><?php foreach ( $service['good'] as $item ) : ?><li><?php echo esc_html( $item ); ?></li><?php endforeach; ?></ul></article>
    <article><span class="decision-status caution">Trade-offs</span><h2><?php echo esc_html( $service['limits_title'] ); ?></h2><ul><?php foreach ( $service['limits'] as $item ) : ?><li><?php echo esc_html( $item ); ?></li><?php endforeach; ?></ul></article>
  </div>
  <p class="service-note"><strong>Implementation note:</strong> <?php echo esc_html( $service['note'] ); ?></p>
  <?php if ( ! empty( $service['case_study'] ) ) : $case = $service['case_study']; ?>
    <section class="case-study"<?php echo 'applied-ai' === $service_key ? ' id="neonlib"' : ''; ?>>
      <header><p class="eyebrow"><?php echo esc_html( $case['label'] ); ?></p><h2><?php echo esc_html( $case['title'] ); ?></h2><p><?php echo esc_html( $case['intro'] ); ?></p></header>
      <div class="case-study-grid">
        <article><span class="card-index"><?php echo esc_html( $case['requirements_label'] ?? 'PRODUCT REQUIREMENTS' ); ?></span><ul><?php foreach ( $case['requirements'] as $item ) : ?><li><?php echo esc_html( $item ); ?></li><?php endforeach; ?></ul></article>
        <article><span class="card-index"><?php echo esc_html( $case['implementation_label'] ?? ( 'applied-ai' === $service_key ? 'ANDROID IMPLEMENTATION' : 'FIREBASE IMPLEMENTATION' ) ); ?></span><ul><?php foreach ( $case['implementation'] as $item ) : ?><li><?php echo esc_html( $item ); ?></li><?php endforeach; ?></ul></article>
        <?php if ( ! empty( $case['google_services'] ) ) : ?><article><span class="card-index"><?php echo esc_html( $case['google_label'] ?? 'GOOGLE SERVICES' ); ?></span><ul><?php foreach ( $case['google_services'] as $item ) : ?><li><?php echo esc_html( $item ); ?></li><?php endforeach; ?></ul></article><?php endif; ?>
      </div>
      <p class="case-study-result"><strong>Result:</strong> <?php echo esc_html( $case['result'] ); ?></p>
      <?php if ( ! empty( $case['link_url'] ) && ! empty( $case['link_label'] ) ) : ?><a class="service-link" href="<?php echo esc_url( $case['link_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $case['link_label'] ); ?></a><?php endif; ?>
    </section>
  <?php endif; ?>
  <div class="service-detail-actions"><a class="button" href="<?php echo esc_url( home_url( '/services/' ) ); ?>">← All services</a><a class="button button-primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Discuss a project</a></div>
</div></section>
<?php get_footer(); ?>
