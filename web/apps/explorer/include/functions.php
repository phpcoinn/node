<?php

function explorer_address_link($address, $short= false) {
	$text  = $address;
	if($short) {
		$text  = truncate_hash($address);
	}
	return '<a href="/apps/explorer/address.php?address='.$address.'">'.$text.'</a>';
}
function explorer_address_pubkey($pubkey, $show = 12) {
	if(!empty($pubkey)) {
		$pubkey_short = substr($pubkey, 0, $show) . "..." .substr($pubkey, -$show);
		return '<a href="/apps/explorer/address.php?pubkey='.$pubkey.'" title="'.$pubkey.'">'.$pubkey_short.'</a>';
	}
}
function explorer_block_link($block_id, $short= false) {
	if(empty($block_id)) return null;
	if($short) {
		$text = substr($block_id, 0, 12) . "..." .substr($block_id, -12);
	} else {
		$text = $block_id;
	}
	return '<a href="/apps/explorer/block.php?id='.$block_id.'" '.($short?'title="'.$block_id.'" data-bs-toggle="tooltip"':'').'>'.$text.'</a>';
}

function explorer_height_link($height) {
    return '<a href="/apps/explorer/block.php?height='.$height.'">'.$height.'</a>';
}

function explorer_tx_link($id, $short=false) {
	if($short) {
		$text = substr($id, 0, 12) . "..." .substr($id, -12);
	} else {
		$text = $id;
	}
	return '<a href="/apps/explorer/tx.php?id='.$id.'" '.($short?'title="'.$id.'" data-bs-toggle="tooltip"':'').'>'.$text.'</a>';
}

function get_data_model($total, $link, $default_sorting = "", $rowsPerPage=10) {
	$pages = ceil($total / $rowsPerPage);
	$page = 1;
	if(isset($_GET['page'])) {
		$page = $_GET['page'];
	}
	if($page<0) {
		$page = 1;
	}
	if($page > $pages) {
		$page = $pages;
	}

	$sorting_query = '';
	if(isset($_GET['sort'])) {
		$sort = $_GET['sort'];
		if(isset($_GET['order'])) {
			$order = $_GET['order'];
		} else {
			$order = 'asc';
		}
		$sorting_query = '&sort='.$sort.'&order='.$order;
	}

	$search = null;
	if(isset($_GET['search'])) {
		$search = $_GET['search'];
		if(is_array($search)) {
			foreach ($search as $key => &$val) {
				if(!is_array($val)) {
					$val = trim($val);
				}
			}
		}
		$search_query = http_build_query(["search"=>$search]);
	}

	$startPage = $page - 2;
	$endPage = $page + 2;
	if($startPage < 1)  $startPage = 1;
	if($endPage > $pages) $endPage = $pages;

	$start = max(0,($page-1)*$rowsPerPage + 1);
	$end = min($start + $rowsPerPage - 1, $total);


		$paginator = '
		<div class="row">
			<div class="col-sm-12 col-md-5 d-flex align-items-center">
				Showing ' . $start . ' to ' . $end . ' of ' . $total . ' entries
			</div>
			<div class="col-sm-12 col-md-7 d-flex justify-content-end align-items-center">
		
        <ul class="pagination mb-0">';
		if ($page > 1) {
			$paginator .= ' 
 				<li class="page-item">
                    <a class="page-link" href="' . $link . '&page=1' . $sorting_query . '&'.$search_query.'" aria-label="First">
                        <span aria-hidden="true">First</span>
                    </a>
                <li>
                <li class="page-item">
                    <a class="page-link" href="' . $link . '&page=' . ($page - 1) . '' . $sorting_query . '&'.$search_query.'" aria-label="Previous">
                        <span aria-hidden="true">Previous</span>
                    </a>
                </li>';
		}
		for ($i = $startPage; $i <= $endPage; $i++) {
			$paginator .= '
				<li class="page-item ' . (($i == $page) ? 'active' : '') . '">
                    <a class="page-link" href="' . $link . '&page=' . $i . '' . $sorting_query . '&'.$search_query.'">' . $i . '</a>
                </li>';
		}
		if ($page < $pages) {
			$paginator .= '
				<li class="page-item">
                    <a class="page-link" href="' . $link . '&page=' . ($page + 1) . '' . $sorting_query . '&'.$search_query.'" aria-label="Next">
                        <span aria-hidden="true">Next</span>
                    </a>
                <li>
                <li class="page-item">
                    <a class="page-link" href="' . $link . '&page=' . $pages . '' . $sorting_query . '&'.$search_query.'" aria-label="Next">
                        <span aria-hidden="true">Last</span>
                    </a>
                </li>';
		}
		$paginator .= '</ul>
		</div>
	</div>
	';

	$start = ($page-1)*$rowsPerPage;

	$sorting = $default_sorting;
	if(isset($_GET['sort'])) {
		$sorting = ' order by '.$_GET['sort'];
		if(isset($_GET['order'])){
			$sorting.= ' ' . $_GET['order'];
		}
	}

	return [
		'page'=>$page,
		'limit'=>$rowsPerPage,
		'paginator'=>$paginator,
		'sort'=>$sort,
		'order'=>$order,
		'search'=>$search,
		'start'=>$start,
		'sorting'=>$sorting,
		"search_query"=>$search_query
	];
}


function sort_column($link, $dm, $column, $name, $align = 'text-end') {
	$s = '<th class="sorting ' . ($dm['sort']==$column ? 'sorting_'.$dm['order'] : '') . '  ' . $align . '">
            <a href="'.$link.'&sort='.$column.'&order=' . ($dm['sort']==$column ? ($dm['order']=='asc' ? 'desc' : 'asc') : 'asc') . '&'.$dm['search_query'].'">
				'.$name.'
			</a>
        </th>';
	return $s;
}

function display_short($string, $show = 12) {
	if(!empty($string)) {
		$string_short = substr($string, 0, $show) . "..." .substr($string, -$show);
		return '<span title="'.$string.'">'.$string_short.'</span>';
	}
}

function durationFormat($seconds)
{
	$a_sec=1;
	$a_min=$a_sec*60;
	$an_hour=$a_min*60;
	$a_day=$an_hour*24;
	$a_week=$a_day*52;
	//$a_month=$a_day*(floor(365/12));
	$a_month=$a_day*30;
	$a_year=$a_day*365;

	$params=2;
	$text='';
	if($seconds>$a_year)
	{
		$years=floor($seconds/$a_year);
		$text.=$years.' y ';
		$seconds=$seconds-($years*$a_year);
		$params--;
	}
	if($params==0) return $text;
	if($seconds>$a_month)
	{
		$months=floor($seconds/$a_month);
		$text.=$months.' mt ';
		$seconds=$seconds-($months*$a_month);
		$params--;
	}
	if($params==0) return $text;
	if($seconds>$a_week)
	{
		$weeks=floor($seconds/$a_week);
		$text.=$weeks.' w ';
		$seconds=$seconds-($months*$a_week);
		$params--;
	}
	if($params==0) return $text;
	if($seconds>$a_day)
	{
		$days=floor($seconds/$a_day);
		$text.=$days.' d ';
		$seconds=$seconds-($days*$a_day);
		$params--;
	}
	if($params==0) return $text;
	$H=gmdate("H", $seconds);
	if($H>0)
	{
		$text.=$H.' h ';
		$params--;
	}
	if($params==0) return $text;
	$M=gmdate("i", $seconds);
	if($M>0)
	{
		$text.=$M.' m ';
		$params--;
	}
	if($params==0) return $text;
	$S=gmdate("s", $seconds);
	$text.=$S.' s ';

	return $text;
}
