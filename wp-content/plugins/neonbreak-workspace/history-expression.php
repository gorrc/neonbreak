<?php
defined('ABSPATH') || exit;
/** Restricted expression parser. Original model text is NEVER returned as executable SQL. */
final class NB_History_Expression {
    private array $tokens=[];
    private int $position=0;
    private array $params=[];
    private array $calendar;
    private int $now;
    private bool $yesterday;
    private bool $roleSpecified=false;
    public static function calendar(?int $now=null,string $timezone='Europe/Zagreb'):array {
        $today=(new DateTimeImmutable('@'.($now??time())))->setTimezone(new DateTimeZone($timezone))->setTime(0,0);
        $utc=new DateTimeZone('UTC');
        return ['yesterday_start'=>$today->modify('-1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),'yesterday_end'=>$today->setTimezone($utc)->format('Y-m-d H:i:s')];
    }
    public static function prompt_context(?int $now=null):string {
        $dates=self::calendar($now);
        return 'For jučer/yesterday use EXACTLY: m.created_at >= :yesterday_start AND m.created_at < :yesterday_end. These placeholders are server-computed calendar boundaries (Europe/Zagreb), not the last 24 hours. Today: '.wp_date('Y-m-d',$now,new DateTimeZone('Europe/Zagreb')).'. UTC bounds: '.wp_json_encode($dates).'.';
    }
    public static function compile(string $expression,string $question='',?int $now=null) {
        try {
            $parser=new self();$parser->now=$now??time();$parser->calendar=self::calendar($parser->now);
            $parser->yesterday=(bool)preg_match('/\b(?:jučer|jucer|yesterday)\b/iu',$question);
            $parser->tokenize($expression);$sql=$parser->expression(0);
            if($parser->position!==count($parser->tokens))throw new InvalidArgumentException('Unexpected expression suffix.');
            // Calendar constraints live outside model OR branches, independently of its interpretation.
            if($parser->yesterday){
                $sql='('.$sql.') AND m.created_at >= %s AND m.created_at < %s';
                $parser->params[]=$parser->calendar['yesterday_start'];$parser->params[]=$parser->calendar['yesterday_end'];
            }
            if(!$parser->roleSpecified){$sql='('.$sql.') AND m.role = %s';$parser->params[]='user';}
            return ['sql'=>$sql,'params'=>$parser->params,'calendar'=>$parser->calendar,'yesterday_enforced'=>$parser->yesterday];
        } catch(InvalidArgumentException $e){return new WP_Error('nb_history_expression',$e->getMessage(),['status'=>400]);}
    }
    private function tokenize(string $input):void {
        if(strlen($input)>6000||!mb_check_encoding($input,'UTF-8'))throw new InvalidArgumentException('Expression is too long or invalid UTF-8.');
        $offset=0;$length=strlen($input);
        while($offset<$length){
            if(preg_match('/\G\s+/u',$input,$match,0,$offset)){$offset+=strlen($match[0]);continue;}
            // Strings permit doubled SQL apostrophes, never backslash escape semantics.
            if(!preg_match("~\\G(?:'([^'\\\\\\x00-\\x1f]|'')*'|:[a-z_]+|[a-z_][a-z_0-9]*(?:\\.[a-z_][a-z_0-9]*)?|[0-9]+|>=|<=|<>|!=|[()=<>-])~i",$input,$match,0,$offset))throw new InvalidArgumentException('Unsupported SQL token.');
            $token=$match[0];$this->tokens[]=$token;$offset+=strlen($token);
            if(count($this->tokens)>256)throw new InvalidArgumentException('Expression is too complex.');
        }
        if(!$this->tokens)throw new InvalidArgumentException('Empty expression.');
    }
    private function peek():string{return strtoupper($this->tokens[$this->position]??'');}
    private function take(string $token):void {if($this->peek()!==strtoupper($token))throw new InvalidArgumentException('Expected '.$token.'.');$this->position++;}
    private function expression(int $depth):string {
        $sql=$this->conjunction($depth);
        while($this->peek()==='OR'){$this->position++;$sql='('.$sql.' OR '.$this->conjunction($depth).')';}
        return $sql;
    }
    private function conjunction(int $depth):string {
        $sql=$this->atom($depth);
        while($this->peek()==='AND'){$this->position++;$sql='('.$sql.' AND '.$this->atom($depth).')';}
        return $sql;
    }
    private function atom(int $depth):string {
        if($depth>12)throw new InvalidArgumentException('Expression nesting is too deep.');
        if($this->peek()==='('){$this->position++;$sql=$this->expression($depth+1);$this->take(')');return '('.$sql.')';}
        $field=strtolower($this->tokens[$this->position++]??'');
        if(!in_array($field,['m.content','m.role','m.created_at'],true))throw new InvalidArgumentException('Column is not permitted.');
        if($field==='m.role')$this->roleSpecified=true;
        $operator=$this->peek();$this->position++;
        $allowed=$field==='m.content'?['LIKE','='] : ($field==='m.role'?['=']:['>=','>','<','<=','=']);
        if(!in_array($operator,$allowed,true))throw new InvalidArgumentException('Operator is not permitted for this column.');
        if($field==='m.created_at')$value=$this->date_value($operator);
        else {
            $value=$this->string_value();
            if($field==='m.role'&&!in_array($value,['user','assistant'],true))throw new InvalidArgumentException('Unknown message role.');
            if($field==='m.content'&&(mb_strlen($value)>200||trim(str_replace(['%','_'],'',$value))===''))throw new InvalidArgumentException('A non-empty search term up to 200 characters is required.');
        }
        $this->params[]=$value;return $field.' '.$operator.' %s';
    }
    private function string_value():string {
        $token=$this->tokens[$this->position++]??'';
        if(strlen($token)<2||$token[0]!=="'"||substr($token,-1)!=="'")throw new InvalidArgumentException('A quoted string is required.');
        return str_replace("''","'",substr($token,1,-1));
    }
    private function date_value(string $operator):string {
        $next=$this->peek();
        if(in_array($next,[':YESTERDAY_START',':YESTERDAY_END'],true)){$key=strtolower(substr($this->tokens[$this->position++],1));$value=$this->calendar[$key];}
        elseif($next==='UTC_TIMESTAMP'){
            $this->take('UTC_TIMESTAMP');$this->take('(');$this->take(')');$this->take('-');$this->take('INTERVAL');
            $number=$this->tokens[$this->position++]??'';
            if(!ctype_digit($number)||(int)$number<1||(int)$number>90)throw new InvalidArgumentException('Rolling period must be 1–90 days.');
            $this->take('DAY');$value=gmdate('Y-m-d H:i:s',$this->now-(int)$number*DAY_IN_SECONDS);
        }else{
            $value=$this->string_value();$date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
            if(!$date||$date->format('Y-m-d H:i:s')!==$value)throw new InvalidArgumentException('Use a valid UTC timestamp.');
        }
        if($this->yesterday && !(($operator==='>='&&$value===$this->calendar['yesterday_start'])||($operator==='<'&&$value===$this->calendar['yesterday_end'])))throw new InvalidArgumentException('Yesterday requires exact calendar boundaries, not a rolling interval.');
        return $value;
    }
}
