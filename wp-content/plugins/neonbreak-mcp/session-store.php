<?php
defined('ABSPATH') || exit;
/** Legacy handshake sessions are isolated by authenticated WordPress owner. */
final class NB_MCP_Session_Store implements \Mcp\Server\Session\SessionStoreInterface {
    private int $owner;
    public function __construct(int $owner) { $this->owner=$owner; }
    private function key(\Symfony\Component\Uid\Uuid $id): string { return 'nb_mcp_'.$this->owner.'_'.hash('sha256',$id->toRfc4122()); }
    public function exists(\Symfony\Component\Uid\Uuid $id): bool { return $this->read($id)!==false; }
    public function read(\Symfony\Component\Uid\Uuid $id): string|false { $value=get_transient($this->key($id));return is_string($value)?$value:false; }
    public function write(\Symfony\Component\Uid\Uuid $id,string $data): bool {
        if(strlen($data)>65536)return false;
        return set_transient($this->key($id),$data,15*MINUTE_IN_SECONDS) || get_transient($this->key($id))===$data;
    }
    public function destroy(\Symfony\Component\Uid\Uuid $id): bool { delete_transient($this->key($id));return true; }
    public function gc(): array { return []; } // WordPress transient TTL enforces expiry on every read.
}
