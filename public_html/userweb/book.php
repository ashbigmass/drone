<?php
header("Pragma: no-cache");
header("Cache-Control: no-store, no-cache, must-revalidate");

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

<!--h2>※ 내 비행 목록</h2-->

<form name="Dform" method="post" action="proc.php">
<input type="hidden" name="idx" value="" />
<input type="hidden" name="referer" value="flight_del" />
</form>

<div>
	<!--li>
		<span class="pull-left paddingL_10"><a href="book_inst.php" class="btn btn-warning">비행계획 등록</a></span>
	</li-->
	<li class="paddingT_10 clear_both">
<table class="table table-border table-hover" id="TBL">
	<thead>
		<tr>
			<th>드론기종</th>
			<th>비행모듈</th>
			<th>비행지역</th>
			<th>비행예정일</th>
			<th>시간</th>
			<th>휴대폰</th>
			<th>비고</th>
			<th>등록일</th>
			<th>관리</th>
		</tr>
	</thead>
	<tbody>
<?
$sql = "select count(b_idx) as cnt from dt_book where view='y' and member_srl='".$srl."'";
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

$sql = "
select
	b.*, d.d_idx, d.d_name, m.m_idx, m.m_nick
from
	dt_book as b
left join dt_drone as d on d.d_idx=b.d_idx
left join dt_module as m on m.m_idx=b.m_idx
where
	b.member_srl='".$srl."' and b.view='y' and d.view='y' and m.view='y'
order by b_idx
limit ".$first.", "._PS_."
";
$ret = $DB->prepare($sql);
$ret->execute();
while($res = $ret->fetch(PDO::FETCH_OBJ)) {
	if($res->confirm=='y') $confirm = '<a href="javascript:;">비행내역</a>';
	else $confirm = '<span style="color:red;">비행미승인</span>';
?>
		<tr>
			<td><?=$res->d_name;?></td>
			<td><?=$res->m_nick;?></td>
			<td><?=$res->b_area;?></td>
			<td class="align_center"><?=$res->b_date;?></td>
			<td class="align_center"><?=$res->b_stime;?> ~ <?=$res->b_etime;?></td>
			<td><?=$res->b_phone;?></td>
			<td><?=$res->b_etc;?></td>
			<td width="160" class="align_center"><?=$res->b_regtime;?></td>
			<td width="120" class="align_center"><?=$confirm;?></td>
		</tr>
<?
}
?>
	</tbody>
</table>

<?=paging($PG);?>

	</li>
</div>
