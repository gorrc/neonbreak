<?php
/**
 * Plugin Name: Neonbreak MCP
 * Description: Authenticated MCP tools for Neonbreak AI Workspace, powered by the official PHP MCP SDK.
 * Version: 0.2.0
 * Requires PHP: 8.1
 */
defined('ABSPATH') || exit;
if (PHP_VERSION_ID < 80100) return;
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/session-store.php';
require_once __DIR__.'/server.php';
require_once __DIR__.'/conversation-search.php';
add_action('rest_api_init',function(){
    register_rest_route('neonbreak-mcp/v1','/mcp',[
        'methods'=>['POST','DELETE'],
        'permission_callback'=>['NB_MCP','permission'],
        'callback'=>['NB_MCP','endpoint'],
    ]);
});
// SDK transports own the JSON-RPC/SSE serialization; avoid WordPress encoding their body twice.
add_filter('rest_pre_serve_request',function($served,$result,$request,$server){
    if ($result instanceof NB_MCP_Response) { echo $result->raw_body;return true; }
    return $served;
},10,4);
add_action('admin_menu',function(){add_options_page('Neonbreak MCP','Neonbreak MCP','manage_options','neonbreak-mcp',function(){
    if(!current_user_can('manage_options'))return;
    echo '<div class="wrap"><h1>Neonbreak MCP</h1><p>'.esc_html(NB_MCP::ready() ? 'MCP is ready. AI Business Assistant can use knowledge and widget conversation tools.' : 'Activate Neonbreak Chat and Neonbreak AI Workspace first.').'</p><h2>HTTP endpoint</h2><code>'.esc_html(rest_url('neonbreak-mcp/v1/mcp')).'</code><p>Authentication: WordPress sign-in with REST nonce, or an existing WordPress Application Password over HTTPS. Advanced access is checked on every request. This endpoint is not anonymous and does not accept AI Search keys.</p><h2>Tools</h2><p><code>search_knowledge(query, limit)</code> — searches only the authenticated user’s active knowledge documents. Uses the existing AI Search limits and Fireworks connection.</p><p><code>search_conversations(query, days, limit, conversation_id)</code> — read-only search of the authenticated owner’s widget conversations. Up to 20 searches/minute; no embeddings.</p><p>The assistant calls the same official SDK transport in-process. No local HTTP loopback or extra hosting is required.</p></div>';
});});
