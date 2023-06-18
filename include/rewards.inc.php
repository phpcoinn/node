<?php


$chain_id = trim(@file_get_contents(dirname(__DIR__)."/chain_id"));
if($chain_id != DEFAULT_CHAIN_ID && file_exists(__DIR__ . "/rewards.".$chain_id.".inc.php")) {
    require_once __DIR__ . "/rewards.".$chain_id.".inc.php";
    return;
}



//	 phase               ,segment   ,start       ,end         ,miner               ,generator ,staker    ,masternode,collateral
//	 0                   ,1         ,2           ,3           ,4                   ,5         ,6         ,7         ,8
const REWARD_SCHEME = [
    ["genesis"           ,""        ,1           ,1           ,103200000           ,0         ,0         ,0         ,0         ],
    ["launch"            ,""        ,2           ,20000       ,8                   ,2         ,0         ,0         ,0         ],
    ["increasing"        ,"1"       ,20001       ,200000      ,3                   ,1         ,2         ,4         ,10000     ],
    ["increasing"        ,"2"       ,200001      ,300000      ,6                   ,2         ,4         ,8         ,15000     ],
    ["increasing"        ,"3"       ,300001      ,400000      ,9                   ,3         ,6         ,12        ,20000     ],
    ["increasing"        ,"4"       ,400001      ,500000      ,12                  ,4         ,8         ,16        ,25000     ],
    ["increasing"        ,"5"       ,500001      ,600000      ,15                  ,5         ,10        ,20        ,30000     ],
    ["increasing"        ,"6"       ,600001      ,700000      ,18                  ,6         ,12        ,24        ,40000     ],
    ["increasing"        ,"7"       ,700001      ,800000      ,21                  ,7         ,14        ,28        ,50000     ],
    ["increasing"        ,"8"       ,800001      ,900000      ,24                  ,8         ,16        ,32        ,60000     ],
    ["increasing"        ,"9"       ,900001      ,1000000     ,27                  ,9         ,18        ,36        ,70000     ],
    ["increasing"        ,"10"      ,1000001     ,1100000     ,30                  ,10        ,20        ,40        ,80000     ],
    ["decreasing"        ,"1"       ,1100001     ,1200000     ,27                  ,9         ,18        ,36        ,80000     ],
    ["decreasing"        ,"2"       ,1200001     ,1300000     ,24                  ,8         ,16        ,32        ,80000     ],
    ["decreasing"        ,"3"       ,1300001     ,1400000     ,21                  ,7         ,14        ,28        ,80000     ],
    ["decreasing"        ,"4"       ,1400001     ,1500000     ,18                  ,6         ,12        ,24        ,80000     ],
    ["decreasing"        ,"5"       ,1500001     ,1600000     ,15                  ,5         ,10        ,20        ,80000     ],
    ["decreasing"        ,"6"       ,1600001     ,1700000     ,12                  ,4         ,8         ,16        ,80000     ],
    ["decreasing"        ,"7"       ,1700001     ,1800000     ,9                   ,3         ,6         ,12        ,80000     ],
    ["decreasing"        ,"8"       ,1800001     ,1900000     ,6                   ,2         ,4         ,8         ,80000     ],
    ["decreasing"        ,"10"      ,1900001     ,PHP_INT_MAX ,0                   ,0         ,0         ,0         ,80000     ],
];
