<?php



// send variable output to error log
function log_str($var){
    $date = date('Y-m-d H:i:s');
    $str = "\n {$date} > ".print_r( $var,1)."\n";
    $type = ini_get('error_log');
    error_log($str,3,$type);
}

class Affiliates extends Transactions {

    public function __construct($p=false){
        parent::__construct($p);
    }

    // get affiliates users id
    // @param $site_user_id
    // @return array affiliates as array(1,4,6,7,8) or array('created_at'=>created_at,'user_id'=>user_id,and so on)
    public static function getAffiliates($fields='site_user',$count=false,$page=false,$per_page=30){

        // retornar username, email, date,default_currency, default_c_currency (campo market), y cuanto tradeó el subordinate en los ultimos 30 dias
        // quiero ver cuanto le gané yo y cuanto ganó el. (affiliate comissions 30 days) y total commisions 30 days.        

        // TODO: INVERTIR LOS FIELDS PARA MATCHEAR

        if($count) $fields = ' count(*) as total ';

        $sql = "
        SELECT {$fields} FROM
            site_users_affiliates 
        WHERE
            affiliate_id=".User::$info['id'].
        "\n ORDER BY id DESC \n"; 

        $a = db_query_array($sql);

        if(!$a) return false;

        if($fields!='site_user')
            return $a;

        // return array as 0=>12312321,1=>55555555,2=>7777777777)
        $af=false;
        foreach($a as $row){
            $af[]=$row['site_user'];
        }
        return $af;
    }

    // return affiliates transaction from last 30 days goup by user, with additional info in every row.
    public static function getAffiliatesTotal30Days($count=false,$paginated=false,$page=0,$results_per_page=30,$overview=false){
        global $CFG;

        // retornar username, date,default_currency, default_c_currency (campo market), y cuanto tradeó el subordinate en los ultimos 30 dias
        // quiero ver cuanto le gané yo y cuanto ganó el. (affiliate comissions 30 days) y total commisions 30 days. 

        /*
        En en backstage:
        http://66.172.10.252/bitdoviz/backstage2/index.php?current_url=edit_page&table=admin_pages&id=75&is_tab=0#
        agregar affiliate_fee_2
        */
 
        if($count){
             $sql = 'SELECT count(site_user) AS total FROM site_users_affiliates WHERE affiliate_id = "'.User::$info['id'].'" ';
             //log_str("\n\n 68 >>>> ".$sql."\n\n");
             return db_query_array($sql);
        }  

        // set price_str and price_str1
        extract( Affiliates::getCurrenciesValues());

       
        $fields = " site_users.user, site_users.default_currency, site_users.default_c_currency,  
                    SUM(\n $price_str \n ) AS 30_day_volume, SUM(\n $price_str1 \n) AS income, affiliates_fee, affiliates_fee1 \n ";

        $sql = "
        SELECT {$fields} FROM
            site_users
            LEFT JOIN site_users_affiliates ON (site_users.id = site_users_affiliates.site_user)
            LEFT JOIN transactions ON (site_users.id = transactions.site_user)       
        WHERE
            site_users_affiliates.affiliate_id=".User::$info['id'].
        "   AND transactions.date >= DATE_ADD(NOW(), INTERVAL -30 DAY) ";
           
 
        if(!$overview)
            $sql.="\n GROUP BY site_users.id \n ORDER BY site_users.id DESC \n";


        if($paginated){
            $start_from = $page * $results_per_page;
            $sql.="\n # add pagination \n LIMIT $start_from,{$results_per_page}";
        }

        log_str($sql);
        return db_query_array($sql);
    }

    public static function getCurrenciesValues(){
        global $CFG;

        $price_str = '(CASE IF(transactions.id = site_users.id,transactions.currency,transactions.currency1) ';
        $price_str1 = '(CASE IF(transactions.id = site_users.id,transactions.currency,transactions.currency1) ';

        foreach ($CFG->currencies as $curr_id => $currency1) {
            if (is_numeric($curr_id))
                continue;

            $conversion = $currency1['usd_ask'];

            $price_str .= " WHEN {$currency1['id']} THEN \n (transactions.btc * IF(transactions.id = site_users.id,transactions.btc_price,transactions.orig_btc_price) * '{$conversion}')";
            $price_str1 .= " WHEN {$currency1['id']} THEN \n (IF(transactions.id = site_users.id,transactions.affiliates_fee,transactions.affiliates_fee1) * IF(transactions.id = site_users.id,transactions.btc_price,transactions.orig_btc_price) * '{$conversion}')";
        }
        $price_str .= ' END)';
        $price_str1 .= ' END)';

        return array('price_str'=>$price_str,'price_str1'=>$price_str1);
    }

    /*
    30-day-income
    number-of-users
    “current cut %” (el porcentaje del fee que gana el Affiliate)
    decimal cuando creo el textbox.eso tiene que venir dle man.
    */
    public static function getOverview(){
        return array(
            'income_30_day'          => Affiliates::getIncome30Days(),
            'number_of_subordinates' => Affiliates::getAffiliates('site_user',1,false,false), // count
            'current_cut'            => db_query_array("SELECT NOW()"),
        );
    }

    //calculo la ganancia total de los ultimos 30 dias 
    public static function getIncome30Days(){
        $raw_30days = Affiliates::getAffiliatesTotal30Days($count=false,$paginated=false,$page=0,$results_per_page=30,$affiliates=1);
        return $raw_30days;
    }
 
 
}
 
