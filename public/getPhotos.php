<?php
require_once __DIR__ . '/admin/LegacyPrivacy.php'; l_start();
header('Content-Type: application/json; charset=utf-8');
$page=max(1,min(100000,(int)($_POST['page'] ?? 1))); $limit=max(1,min(50,(int)($_POST['limit'] ?? 6))); $offset=($page-1)*$limit;
$where=l_filter('album');
$total=(int)c_one('SELECT COUNT(*) AS total FROM loveimg WHERE '.$where)['total'];
$rows=c_all('SELECT imgUrl,imgDatd,imgText FROM loveimg WHERE '.$where.' ORDER BY id DESC LIMIT ?,?', 'ii', array($offset,$limit));
$data=array(); foreach($rows as $row) $data[]=array('img'=>html_entity_decode(l_image_url($row['imgUrl']),ENT_QUOTES,'UTF-8'),'date'=>html_entity_decode($row['imgDatd'],ENT_QUOTES,'UTF-8'),'text'=>html_entity_decode($row['imgText'],ENT_QUOTES,'UTF-8'));
echo json_encode(array('code'=>200,'data'=>$data,'total'=>$total,'page'=>$page,'limit'=>$limit),JSON_UNESCAPED_UNICODE);
