<?php

//	 phase               ,segment   ,start       ,end         ,miner               ,generator ,staker    ,masternode,collateral
//	 0                   ,1         ,2           ,3           ,4                   ,5         ,6         ,7         ,8         
const REWARD_SCHEME = [
	["genesis"           ,""        ,1           ,1           ,GENESIS_REWARD      ,0         ,0         ,0         ,10000     ],
	["launch"            ,""        ,2           ,100000      ,9                   ,1         ,0         ,0         ,10000     ],
	["mining"            ,"1"       ,100001      ,110000      ,9                   ,1         ,0         ,0         ,10000     ],
	["mining"            ,"2"       ,110001      ,120000      ,18                  ,2         ,0         ,0         ,10000     ],
	["mining"            ,"3"       ,120001      ,130000      ,27                  ,3         ,0         ,0         ,10000     ],
	["mining"            ,"4"       ,130001      ,140000      ,36                  ,4         ,0         ,0         ,10000     ],
	["mining"            ,"5"       ,140001      ,150000      ,45                  ,5         ,0         ,0         ,10000     ],
	["mining"            ,"6"       ,150001      ,152000      ,54                  ,6         ,0         ,0         ,10000     ],
	["mining"            ,"7"       ,152001      ,154000      ,63                  ,7         ,0         ,0         ,10000     ],
	["mining"            ,"8"       ,154001      ,156000      ,72                  ,8         ,0         ,0         ,10000     ],
	["mining"            ,"9"       ,156001      ,158000      ,81                  ,9         ,0         ,0         ,10000     ],
	["mining"            ,"10"      ,158001      ,160000      ,90                  ,10        ,0         ,0         ,10000     ],
	["combined"          ,"1"       ,160001      ,210000      ,81                  ,9         ,0         ,10        ,10000     ],
	["combined"          ,"2"       ,210001      ,260000      ,72                  ,8         ,0         ,20        ,10000     ],
	["combined"          ,"3"       ,260001      ,310000      ,63                  ,7         ,0         ,30        ,10000     ],
	["combined"          ,"4"       ,310001      ,360000      ,54                  ,6         ,0         ,40        ,10000     ],
	["combined"          ,"5"       ,360001      ,410000      ,45                  ,5         ,0         ,50        ,10000     ],
	["combined"          ,"6"       ,410001      ,460000      ,36                  ,4         ,0         ,60        ,10000     ],
	["combined"          ,"7"       ,460001      ,500000      ,27                  ,3         ,0         ,70        ,10000     ],
	["combined"          ,"7.1"     ,500001      ,510000      ,24                  ,3         ,3         ,70        ,10000     ],
	["combined"          ,"8"       ,510001      ,560000      ,16                  ,2         ,2         ,80        ,10000     ],
	["combined"          ,"9"       ,560001      ,570000      ,8                   ,1         ,1         ,90        ,12000     ],
	["combined"          ,"9.1"     ,570001      ,580000      ,8                   ,1         ,1         ,90        ,14000     ],
	["combined"          ,"9.2"     ,580001      ,610000      ,8                   ,1         ,1         ,90        ,16000     ],
	["combined"          ,"10"      ,610001      ,660000      ,20                  ,10        ,10        ,60        ,20000     ],
	["deflation"         ,"1"       ,660001      ,760000      ,18                  ,9         ,9         ,54        ,25000     ],
	["deflation"         ,"2"       ,760001      ,860000      ,16                  ,8         ,8         ,48        ,30000     ],
	["deflation"         ,"3"       ,860001      ,960000      ,14                  ,7         ,7         ,42        ,35000     ],
	["deflation"         ,"4"       ,960001      ,1060000     ,12                  ,6         ,6         ,36        ,40000     ],
	["deflation"         ,"5"       ,1060001     ,1160000     ,10                  ,5         ,5         ,30        ,40000     ],
	["deflation"         ,"6"       ,1160001     ,1260000     ,8                   ,4         ,4         ,24        ,40000     ],
	["deflation"         ,"7"       ,1260001     ,1360000     ,6                   ,3         ,3         ,18        ,40000     ],
	["deflation"         ,"8"       ,1360001     ,1460000     ,4                   ,2         ,2         ,12        ,40000     ],
	["deflation"         ,"9"       ,1460001     ,1560000     ,2                   ,1         ,1         ,6         ,40000     ],
	["deflation"         ,"10"      ,1560001     ,PHP_INT_MAX ,0                   ,0         ,0         ,0         ,40000     ],
];
