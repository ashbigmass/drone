<?php
include 'var.php';
include __FN__;

_HEAD_();

//echo $_SESSION['member_srl'];	## 회원 고유번호
$srl = $_SESSION['member_srl'];
if(!$srl) {
	alert("로그인 정보가 없습니다");
	loca("/");
	exit;
}
$DB = DBCon();
?>

<style>
#TBL thead th {text-align:center;background-color:#B1ADAE;color:#fff;}
</style>
<script language="javascript">
<!--
function Drone_DEL(N, NN) {
	var name = NN.replace(/\+/g, ' ');
	if(confirm("선택하신\n\n드론명 : \""+decodeURI(name)+"\"\n\n장비를 삭제하시겠습니까?")!=false) {
		if(confirm("삭제하시면 기존의 비행내역도 삭제됩니다.\n\n진행하시겠습니까?")!=false) {
			document.Dform.idx.value=N;
			document.Dform.submit();
		}
	}
}
//-->
</script>

<!--h2>※ 내 드론 목록</h2-->

<form name="Dform" method="post" action="/userweb/proc.php">
<input type="hidden" name="idx" value="" />
<input type="hidden" name="dt_referer" value="drone_del" />
</form>

<div>
	<!--li>
		<span class="pull-left paddingL_10"><a href="/index.php?mid=mydrone_reg" class="btn btn-success">내 드론 등록</a></span>
		<span class="pull-left paddingL_10"><a href="module.php" class="btn btn-warning">내 위치모듈 목록</a></span>
	</li-->
	<li class="paddingT_10 clear_both">
<table class="table table-border table-hover" id="TBL">
	<thead>
		<tr>
			<th>제조사</th>
			<th>모델명</th>
			<th>비고</th>
			<th>등록일</th>
			<th>관리</th>
		</tr>
	</thead>
	<tbody>
<?
$sql = "select count(d_idx) as cnt from dt_drone where view='y' and member_srl='".$srl."'";
$al1 = $DB->prepare($sql);
$al1->execute();
$all = $al1->fetch(PDO::FETCH_OBJ);

$PG->size = _PS_;
$PG->now = _PN_;
$PG->all = $all->cnt;
$PG->block = ceil($PG->all / _PS_);
$PG->nb = ceil(_PN_ / _PB_);
$tmp = ($PG->nb * _PB_) - (_PB_-1);
$PG->start = ($tmp <= 1) ? 1 : $tmp;
$tmp = ($PG->nb * _PB_);
$PG->end = ($PG->all <= $tmp) ? $PG->all : $tmp;
$first = ($PG->now - 1) * _PS_;

$sql = "select * from dt_drone where member_srl='".$srl."' and view='y' order by d_name asc limit ".$first.", "._PS_;
$ret = $DB->prepare($sql);
$ret->execute();
while($res = $ret->fetch(PDO::FETCH_OBJ)) {
?>
		<tr>
			<td><?=$res->d_comp;?></td>
			<td><?=$res->d_name;?></td>
			<td><?=$res->d_etc;?></td>
			<td width="160" class="align_center"><?=$res->d_regtime;?></td>
			<td width="120" class="align_center">
				<a href="javascript:;">비행내역</a> |
				<a href="javascript:;" onclick="javascript:Drone_DEL('<?=$res->d_idx;?>', '<?=URLEncode($res->d_name);?>');">삭제</a>
			</td>
		</tr>
<?
}
?>
	</tbody>
</table>

<?=paging($PG);?>

	</li>
</div>

<script>$(function() {$("#pdate").datepicker({dateFormat: 'yy-mm-dd', minDate: '<?=_TODAY_;?>'});});</script>
