<?php
defined('ABSPATH') || exit;
/** Admin-owned account limits, separate from user-editable chat presentation. */
final class NB_Workspace_Limits {
    public static function defaults() { return ['search_daily'=>100,'search_minute'=>10,'documents'=>10]; }
    public static function ranges() { return ['search_daily'=>[1,100000],'search_minute'=>[1,1000],'documents'=>[1,100]]; }
    public static function get($uid) {
        $saved=get_user_meta($uid,'_nb_workspace_limits',true);$saved=is_array($saved)?$saved:[];$out=self::defaults();
        foreach(self::ranges()as$key=>$range){$value=filter_var($saved[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>$range[0],'max_range'=>$range[1]]]);if($value!==false)$out[$key]=$value;}
        return $out;
    }
    public static function save($uid,array $input) {
        if(!current_user_can('manage_options'))return new WP_Error('forbidden','Nemate pravo mijenjati limite.',['status'=>403]);
        if(!$uid||!get_userdata($uid))return new WP_Error('user','Korisnik nije pronađen.',['status'=>404]);
        $values=[];foreach(self::ranges()as$key=>$range){$raw=$input[$key]??null;$value=is_scalar($raw)?filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>$range[0],'max_range'=>$range[1]]]):false;if($value===false)return new WP_Error('limits','Unesite valjane cijele brojeve u prikazanom rasponu.',['status'=>400]);$values[$key]=$value;}
        update_user_meta($uid,'_nb_workspace_limits',$values);
        return get_user_meta($uid,'_nb_workspace_limits',true)===$values ? true : new WP_Error('storage','Limiti nisu spremljeni.',['status'=>503]);
    }
}
add_action('admin_post_nb_workspace_limits',function(){
    if(!current_user_can('manage_options'))wp_die('Forbidden.','',['response'=>403]);
    $uid=absint($_POST['user']??0);check_admin_referer('nb_workspace_limits_'.$uid);
    $input=[];foreach(array_keys(NB_Workspace_Limits::defaults())as$key)$input[$key]=isset($_POST[$key])&&is_string($_POST[$key])?wp_unslash($_POST[$key]):null;
    $result=NB_Workspace_Limits::save($uid,$input);
    $back=['page'=>'nb-workspace-admin','tab'=>'usage','limits_saved'=>is_wp_error($result)?'error':'1','scope'=>($_POST['return_scope']??'')==='all'?'all':'workspace','nwa_page'=>max(1,absint($_POST['return_page']??1))];
    if(is_string($_POST['return_s']??null))$back['s']=mb_substr(sanitize_text_field(wp_unslash($_POST['return_s'])),0,100);
    wp_safe_redirect(add_query_arg($back,admin_url('admin.php')).'#nwa-user-'.$uid);exit;
});
