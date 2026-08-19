<?php get_header(); ?>
<section class="page-hero"><div class="shell"><p class="eyebrow"><span></span>Services</p><h1>Focused systems for <em>meaningful work.</em></h1><p>From a first use case to production delivery, we combine product thinking, AI engineering and practical implementation.</p></div></section>
<section class="section"><div class="shell service-list">
<?php $services = array(
'automation' => array('Operational automation','We connect systems, reduce manual handling and build reliable workflows that keep people in control.','Workflow mapping','Tool and API integration','Document processing','Review and exception handling'),
'products' => array('Applied AI products','Custom assistants, semantic search and decision support built for a defined job—not a generic demo.','Knowledge assistants','Intelligent search','Classification and extraction','Internal product tools'),
'data' => array('Data and knowledge foundations','We organise the information layer your automation and AI depend on, with quality and ownership built in.','Data pipelines','Knowledge architecture','Retrieval systems','Evaluation and monitoring'),
'advisory' => array('AI opportunity and delivery advisory','A grounded path from opportunity to investment decision, with risks and next steps made explicit.','Use-case discovery','Feasibility assessment','Prototype planning','Technical due diligence'));
foreach ($services as $id => $service) : ?><article id="<?php echo esc_attr($id); ?>" class="service-row"><span class="service-row__index">0<?php echo esc_html(array_search($id,array_keys($services),true)+1); ?></span><div><h2><?php echo esc_html($service[0]); ?></h2><p><?php echo esc_html($service[1]); ?></p></div><ul><?php foreach(array_slice($service,2) as $item) echo '<li>'.esc_html($item).'</li>'; ?></ul></article><?php endforeach; ?>
</div></section><section class="cta-section"><div class="shell cta-panel"><div><p class="eyebrow"><span></span>Not sure where to start?</p><h2>Bring us the problem, not a specification.</h2></div><a class="button button--light" href="<?php echo esc_url(home_url('/contact/')); ?>">Talk to us <span>↗</span></a></div></section>
<?php get_footer(); ?>

