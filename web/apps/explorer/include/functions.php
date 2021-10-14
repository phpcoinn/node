<?php

function explorer_address_link($address) {
	return '<a target="_blank" href="/apps/explorer/address.php?address='.$address.'">'.$address.'</a>';
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

function explorer_tx_link($id, $short=false) {
	if($short) {
		$text = substr($id, 0, 12) . "..." .substr($id, -12);
	} else {
		$text = $id;
	}
	return '<a href="/apps/explorer/tx.php?id='.$id.'" '.($short?'title="'.$id.'" data-bs-toggle="tooltip"':'').'>'.$text.'</a>';
}

function get_data_model($total, $link) {
	$rowsPerPage = 10;
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

	$sorting = '';
	if(isset($_GET['sort'])) {
		$sort = $_GET['sort'];
		if(isset($_GET['order'])) {
			$order = $_GET['order'];
		} else {
			$order = 'asc';
		}
		$sorting = '&sort='.$sort.'&order='.$order;
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
                    <a class="page-link" href="' . $link . '&page=1' . $sorting . '" aria-label="First">
                        <span aria-hidden="true">First</span>
                    </a>
                <li>
                <li class="page-item">
                    <a class="page-link" href="' . $link . '&page=' . ($page - 1) . '' . $sorting . '" aria-label="Previous">
                        <span aria-hidden="true">Previous</span>
                    </a>
                </li>';
		}
		for ($i = $startPage; $i <= $endPage; $i++) {
			$paginator .= '
				<li class="page-item ' . (($i == $page) ? 'active' : '') . '">
                    <a class="page-link" href="' . $link . '&page=' . $i . '' . $sorting . '">' . $i . '</a>
                </li>';
		}
		if ($page < $pages) {
			$paginator .= '
				<li class="page-item">
                    <a class="page-link" href="' . $link . '&page=' . ($page + 1) . '' . $sorting . '" aria-label="Next">
                        <span aria-hidden="true">Next</span>
                    </a>
                <li>
                <li class="page-item">
                    <a class="page-link" href="' . $link . '&page=' . $pages . '' . $sorting . '" aria-label="Next">
                        <span aria-hidden="true">Last</span>
                    </a>
                </li>';
		}
		$paginator .= '</ul>
		</div>
	</div>
	';


	return [
		'page'=>$page,
		'limit'=>$rowsPerPage,
		'paginator'=>$paginator,
		'sort'=>$sort,
		'order'=>$order
	];
}


function sort_column($link, $dm, $column, $name) {
	$s = '<th class="sorting ' . ($dm['sort']==$column ? 'sorting_'.$dm['order'] : '') . ' text-end">
            <a href="'.$link.'&sort='.$column.'&order=' . ($dm['sort']==$column ? ($dm['order']=='asc' ? 'desc' : 'asc') : 'asc') . '">
				'.$name.'
			</a>
        </th>';
	return $s;
}

