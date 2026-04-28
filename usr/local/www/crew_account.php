<?php
require_once('guiconfig.inc');
include_once("auth.inc");
include_once("common_ui.inc");
include_once("terminal_status.inc");
include_once("manage_crew_wifi_account.inc");

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    export_wifi_csv();
}

global $adminlogin;
$controldisplay="";
$addbutton="";
if($adminlogin==="admin"||$adminlogin==="vesseladmin") {
    $controldisplay = '<button class="btn md line-gray" onclick="confirm_exportCsv()"><i class="ic-reset gray"></i>Export CSV</button>
                       <button class="btn md line-gray" onclick="confirm_resetPw()"><i class="ic-reset gray"></i>Reset PW</button>
                       <button class="btn md line-gray" onclick="confirm_setRandomPw()"><i class="ic-reset gray"></i>SET RANDOM PW</button>
                       <button class="btn md line-gray" onclick="confirm_resetData()"><i class="ic-reset gray"></i>Reset Data</button>
                            <button class="btn md line-gray" onclick="confirm_checkPw()"><i class="ic-check gray"></i>Check PW</button>
                            <button class="btn md line-gray" onclick="confirm_delUser()"><i class="ic-delete gray"></i>Delete</button></>';
    $setupbutton = '<button class="btn-setting" onclick="popOpenAndDim(\'pop-modify-manage\', true)">Modify Voucher</button>';
    $addbutton = '<button class="btn-setting" onclick="popOpenAndDim(\'pop-set-manage\', true)">Add Voucher</button>';
}
else if($adminlogin==="customer"){
    $controldisplay = '<button class="btn md line-gray" onclick="confirm_resetPw()"><i class="ic-reset gray"></i>Reset PW</button>';
}
else{
    $controldisplay="";
}
$cpzone='crew';

if (isset($cpzone) && !empty($cpzone) && isset($a_cp[$cpzone]['zoneid'])) {
    $cpzoneid = $a_cp[$cpzone]['zoneid'];
}

if (($_GET['act'] == "del") && !empty($cpzone)) {
    captiveportal_disconnect_client($_GET['id'], 6);
}
if (!empty($_POST['schedule_json']) && !empty($_POST['userid'])) {
    $userid = $_POST['userid'];
    $schedule = json_decode($_POST['schedule_json'], true);

    if (!is_array($schedule)) {
        $schedule = [];
    }
    set_scheduler($userid, $schedule);
    header("Location: crew_account_processing.php");
    exit;
}
if ($_POST['description'] && $_POST['userid']) {
    $description=$_POST['description'];
    $userid=$_POST['userid'];
    set_description($userid, $description);
    echo '<script> location.replace("crew_account_processing.php");</script>';
}
//print_r($_POST);

$table_contents = draw_wifi_contents();
$gateways = return_gateways_array();
$gateways_status = return_gateways_status(true);
$terminaltypeoption='<option value="">Auto</option>';
foreach ($gateways as $gname => $gateway){
    if (!startswith($gateway['terminal_type'], 'vpn')){
        $terminaltypeoption .= '<option value="'.$gname.'">'.$gname.'</option>';
    }
}

if (isset($_POST['modifyusers'])) {

    // 1) 기본 POST 값
    $userlist = $_POST['userlist'] ?? [];

    // 2) modifydata 파싱
    $modifydata = [];
    if (!empty($_POST['modifydata'])) {
        parse_str($_POST['modifydata'], $modifydata);
    }

    // 이후 처리
    modify_wifi_user($userlist, $modifydata);
    exit;
}

if(isset($_POST['resetpw'])){ reset_wifi_user_pw($_POST['userlist']); exit(0);}
if(isset($_POST['setrandompw'])){ reset_random_wifi_user_pw($_POST['userlist']); exit(0);}
if(isset($_POST['resetdata'])){reset_wifi_user($_POST['userlist']);exit(0);}
if(isset($_POST['deluser'])){del_wifi_user($_POST['userlist']);exit(0);}
if ($_POST['dataamount']){
    create_wifi_user($_POST['dataamount'], $_POST['vouchernumber'], $_POST['randpwd'], $_POST['terminaltype'], $_POST['timeperiod']);
    echo '<script> location.replace("crew_account_processing.php");</script>';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <?php echo print_css_n_head();?>
    <style>
        /* ===== Scheduler Popup UI ===== */
        .sched-popup .pop-cont {
            padding: 20px 24px;
            overflow-x: auto;
        }

        .sched-setup-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            table-layout: fixed;
        }

        .sched-setup-table th {
            background: #f1f3f5;
            color: #495057;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            text-align: center;
            border-bottom: 2px solid #dee2e6;
        }

        .sched-setup-table td {
            padding: 14px 10px;
            text-align: center;
            vertical-align: middle;
            border-bottom: 1px solid #f1f3f5;
        }

        .sched-setup-table tr:last-child td {
            border-bottom: none;
        }

        .sched-setup-table tr:hover {
            background: #f8f9fa;
        }
        <style>
         .sched-popup {
             width: 780px;
             max-width: 95%;
             position: fixed;
             left: 50%;
             top: 50%;
             transform: translate(-50%, -50%);
             background: #071630;
             border-radius: 24px;
             box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
             border: 1px solid rgba(130, 160, 210, 0.18);
             overflow: hidden;
             color: #ffffff;
             font-family: Arial, Helvetica, sans-serif;
             z-index: 9999;
         }

        .sched-popup .pop-head {
            position: relative;
            padding: 22px 28px 10px 28px;
        }

        .sched-popup .pop-head .title {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            line-height: 1.2;
            color: #ffffff;
        }

        .sched-popup .pop-head .subtitle {
            margin: 6px 0 0 0;
            font-size: 16px;
            color: #8fb1d8;
            font-weight: 500;
        }

        .sched-popup .sched-close {
            position: absolute;
            right: 18px;
            top: 18px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1px solid rgba(153, 180, 220, 0.16);
            background: rgba(255, 255, 255, 0.06);
            color: #a9bddb;
            font-size: 24px;
            line-height: 42px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .sched-popup .sched-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .sched-popup .pop-cont {
            padding: 18px 20px 18px 20px;
        }

        .sched-setup-wrap {
            border: 1px solid rgba(124, 155, 202, 0.18);
            border-radius: 10px;
            overflow: hidden;
            background: rgba(6, 20, 44, 0.35);
        }

        .sched-setup-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .sched-setup-table thead th {
            height: 48px;
            padding: 0 10px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #8fb1d8;
            background: rgba(255, 255, 255, 0.02);
            border-bottom: 1px solid rgba(124, 155, 202, 0.16);
            text-align: center;
        }

        .sched-setup-table tbody tr {
            border-bottom: 1px solid rgba(124, 155, 202, 0.12);
        }

        .sched-setup-table tbody tr:last-child {
            border-bottom: 0;
        }

        .sched-setup-table tbody td {
            height: 62px;
            padding: 0 10px;
            text-align: center;
            vertical-align: middle;
        }

        .sched-no-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.08);
            color: #8fb1d8;
            font-size: 12px;
            font-weight: 700;
        }

        .sched-act-check {
            appearance: none;
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 5px;
            border: 1px solid rgba(124, 155, 202, 0.45);
            background: transparent;
            cursor: pointer;
            position: relative;
        }

        .sched-act-check:checked {
            background: #0fc995;
            border-color: #0fc995;
        }

        .sched-act-check:checked::after {
            content: "";
            position: absolute;
            left: 5px;
            top: 1px;
            width: 4px;
            height: 9px;
            border: solid #ffffff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .sched-time-group {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .sched-time-select {
            min-width: 52px;
            height: 28px;
            padding: 0 8px;
            border-radius: 6px;
            border: 1px solid rgba(124, 155, 202, 0.35);
            background: rgba(255, 255, 255, 0.03);
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            outline: none;
            cursor: pointer;
        }

        .sched-time-colon {
            color: #8fb1d8;
            font-weight: 700;
            font-size: 14px;
            display: inline-block;
            margin: 0 2px;
        }

        .sched-arrow {
            color: #a8c0e4;
            font-size: 18px;
            font-weight: 700;
        }

        .sched-days {
            display: inline-flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 5px;
            max-width: 145px;
        }

        .sched-day {
            appearance: none;
            -webkit-appearance: none;
            min-width: 34px;
            height: 20px;
            padding: 0 7px;
            border-radius: 4px;
            border: 1px solid rgba(124, 155, 202, 0.12);
            background: rgba(255, 255, 255, 0.05);
            color: #8fb1d8;
            font-size: 10px;
            font-weight: 700;
            line-height: 18px;
            cursor: pointer;
            transition: 0.15s ease;
        }

        .sched-day.active {
            background: rgba(15, 201, 149, 0.16);
            border-color: rgba(15, 201, 149, 0.45);
            color: #d8fff4;
        }

        .sched-popup .pop-foot {
            padding: 18px 24px 24px 24px;
            border-top: 1px solid rgba(124, 155, 202, 0.12);
            display: flex;
            justify-content: center;
            gap: 14px;
        }

        .sched-popup .btn {
            min-width: 130px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid transparent;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .sched-popup .fill-mint {
            background: #0fc995;
            color: #ffffff;
            border-color: #0fc995;
        }

        .sched-popup .fill-mint:hover {
            filter: brightness(1.05);
        }

        .sched-popup .fill-dark {
            background: rgba(255, 255, 255, 0.05);
            color: #d8e6fb;
            border-color: rgba(124, 155, 202, 0.18);
        }

        .sched-popup .fill-dark:hover {
            background: rgba(255, 255, 255, 0.08);
        }
    </style>
</head>
<body>
<div id="wrapper">
    <?php echo print_sidebar(basename($_SERVER['PHP_SELF']));?>
    <div id="content">
        <div class="headline-wrap">
            <div class="title-area">
                <p class="headline">Manage Crew Account</p>
            </div>
            <div class="etc-area">
                <div style="display:flex; align-items:center; gap:20px;">
                    <?= $setupbutton ?>
                    <div style="flex:1;"></div>
                    <?= $addbutton ?>
                </div>            </div>
        </div>

        <div class="contents">
            <div class="container">
                <div class="manage-wrap">
                    <div class="list-top" style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:14px;">
                        <div class="search-area" style="display:flex; align-items:flex-end; justify-content:flex-start; flex:0 0 auto;">
                            <?php echo draw_wifi_userid_search_box(); ?>
                        </div>

                        <div class="btn-area" style="display:flex; align-items:center; justify-content:flex-end; gap:8px; flex:1 1 auto; flex-wrap:wrap;">
                            <?= $controldisplay ?>
                        </div>
                    </div>
                    <div class="list-wrap v1">
                        <div class="sort-area">
                            <div class="inner">
                                <select name="" id="" class="select v1">
                                    <option value="">ID</option>
                                    <option value="">Description</option>
                                    <option value="">Duty</option>
                                    <option value="">Type</option>
                                    <option value="">Update</option>
                                    <option value="">Usage state</option>
                                    <option value="">Online</option>
                                    <option value="">Topup</option>
                                </select>
                                <button class="btn-ic btn-sort"></button>
                            </div>
                        </div>
                        <table>
                            <colgroup>
                                <col style="width: 5%;">
                                <col style="width: 10%;">
                                <col style="width: 15%;">
                                <col style="width: 5%;">
                                <col style="width: 10%;">
                                <col style="width: 10%;">
                                <col style="width: 20%;">
                                <col style="width: 10%;">
                                <!--col style="width: 15%;"-->
                            </colgroup>
                            <thead>
                            <tr>
                                <th>
                                    <div class="check v1">
                                        <input type="checkbox" name="userselectall" id="userselectall" onclick="selectAll(this)">
                                        <label for="userselectall"></label>
                                    </div>
                                </th>
                                <th>ID<button class="btn-ic btn-sort"></button></th>
                                <th>Description<button class="btn-ic btn-sort"></button></th>
                                <th>Duty<button class="btn-ic btn-sort"></button></th>
                                <th>Type<button class="btn-ic btn-sort"></button></th>
                                <th>Update<button class="btn-ic btn-sort"></button></th>
                                <th>Usage state<button class="btn-ic btn-sort"></button></th>
                                <th>Online<button class="btn-ic btn-sort"></button></th>
                                <!--<th><button class="btn-ic btn-sort"></button></th>-->
                            </tr>
                            </thead>
                            <tbody id="crew_account_table">
                            <?= $table_contents;?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<form name="modifyusers" id='modifyusers' method="post" action="/crew_account.php">
    <div class="popup layer pop-modify-manage">
        <div class="pop-head">
            <p class="title">Modify Voucher</p>
        </div>
        <div class="pop-cont">
            <div class="form">
                <div class="form-tit">
                    <p class="tit">Data limit (Mbytes)</p>
                </div>
                <div class="form-cont">
                    <!--input type="text" name="datalimit" id="datalimit"-->
                    <input
                            type="text"
                            name="datalimit"
                            id="datalimit"
                            inputmode="numeric"
                            autocomplete="off"
                            pattern="[0-9]*"
                            aria-label="Data limit (Mbytes)"
                    >
                </div>
            </div>
            <div class="form">
                <div class="form-tit">
                    <br>
                    <p class="tit">Time limit (Time minutes)</p>
                </div>
                <div class="form-cont">
                    <input type="text" name="timelimit" id="timelimit"
                           placeholder="Time based limit, NOT IMPLEMENTED YET"
                           inputmode="numeric" autocomplete="off" pattern="[0-9]*">
                </div>
            </div>
            <div class="form mt20">
                <div class="form-tit">
                    <p class="tit">Data speed (Kbps)</p>
                </div>

                <div class="form-cont" style="display:flex; gap:10px;">
                    <input type="text" name="downspeed" id="downspeed" style="width:100%;"
                           placeholder="Download Kbps, Experimental"
                           inputmode="numeric" autocomplete="off" pattern="[0-9]*">

                    <input type="text" name="upspeed" id="upspeed" style="width:100%;"
                           placeholder="Upload Kbps, Experimental"
                           inputmode="numeric" autocomplete="off" pattern="[0-9]*">

                </div>
            </div>

            <hr class="line v1 mt30">
            <div class="form mt30">
                <div class="form-tit">
                    <p class="tit">Terminal Type</p>
                </div>
                <div class="form-cont">
                    <select name="terminaltype" id="terminaltype" class="select v1">
                        <?php echo $terminaltypeoption;?>
                    </select>
                </div>
                <div class="form-tit">
                    <p class="tit">Reset every...</p>
                </div>
                <div class="form-cont">
                    <select name="timeperiod" id="timeperiod" class="select v1">
                        <option value="Monthly">Monthly</option>
                        <option value="half-Monthly">Half-Monthly</option>
                        <option value="Weekly">Weekly</option>
                        <option value="Daily">Daily</option>
                        <option value="Forever">one-time</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="pop-foot">
            <button type='button' class="btn md fill-mint" onclick="submit_modifyusers()"><i class="ic-submit"></i>APPLY</button>
            <button type='button' class="btn md fill-dark" onclick="popClose('pop-modify-manage')"><i class="ic-cancel"></i>CANCEL</button>
        </div>
    </div>
</form>
<form name="registerusers" id='registerusers' method="post" action="/crew_account.php">
    <div class="popup layer pop-set-manage">
        <div class="pop-head">
            <p class="title">Create Voucher</p>
        </div>
        <div class="pop-cont">
            <div class="form">
                <div class="form-tit">
                    <p class="tit">Allow data (MB)</p>
                </div>
                <div class="form-cont">
                    <input type="text" name="dataamount" id="dataamount"
                           inputmode="numeric" autocomplete="off" pattern="[0-9]*">                </div>
            </div>
            <div class="form mt20">
                <div class="form-tit">
                    <p class="tit"># of Vouchers</p>
                </div>
                <div class="form-cont">
                    <input type="text" name="vouchernumber" id="vouchernumber"
                           inputmode="numeric" autocomplete="off" pattern="[0-9]*">                </div>
            </div>

            <div class="check v1 mt30">
                <input type="checkbox" name="randpwd" id="randpwd" value="randpwd">
                <label for="randpwd">
                    <p>Generate random password?</p>
                </label>
            </div>
            <hr class="line v1 mt30">
            <div class="form mt30">
                <div class="form-tit">
                    <p class="tit">Terminal Type</p>
                </div>
                <div class="form-cont">
                    <select name="terminaltype" id="terminaltype" class="select v1">
                        <?php echo $terminaltypeoption;?>


                    </select>
                </div>
                <div class="form-tit">
                    <p class="tit">Reset every...</p>
                </div>
                <div class="form-cont">
                    <select name="timeperiod" id="timeperiod" class="select v1">
                        <option value="Monthly">Monthly</option>
                        <option value="half-Monthly">Half-Monthly</option>
                        <option value="Weekly">Weekly</option>
                        <option value="Daily">Daily</option>
                        <option value="Forever">one-time</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="pop-foot">
            <button type='button' class="btn md fill-mint" onclick="submit_registerusers()"><i class="ic-submit"></i>APPLY</button>
            <button type='button' class="btn md fill-dark" onclick="popClose('pop-set-manage')"><i class="ic-cancel"></i>CANCEL</button>
        </div>
    </div>
</form>
<!--form name="crewscheduler" id="crewscheduler" method="post" action="/crew_account.php">
    <input type="hidden" name="userid" id="userIdHidden">
    <input type="hidden" name="schedule_json" id="scheduleJsonHidden">

    <div class="popup layer pop-set-scheduler sched-popup"
         style="width:780px; max-width:95%; position:fixed; left:50%; top:50%; transform:translate(-50%, -50%);">
        <div class="pop-head">
            <p class="title">Suspension Setup</p>
        </div>

        <div class="pop-cont sched-modal-body">
            <table class="sched-setup-table">
                <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th style="width:50px">Act</th>
                    <th>From</th>
                    <th style="width:30px"></th>
                    <th>To</th>
                    <th style="width:200px">Day</th>
                </tr>
                </thead>
                <tbody id="sched-body"></tbody>
            </table>
        </div>

        <div class="pop-foot sched-modal-footer">
            <button type="button" class="btn md fill-mint" onclick="submit_crewscheduler()">
                <i class="ic-submit"></i>APPLY
            </button>
            <button type="button" class="btn md fill-dark" onclick="popClose('pop-set-scheduler')">
                <i class="ic-cancel"></i>CANCEL
            </button>
        </div>
    </div>
</form-->

<form name="crewscheduler" id="crewscheduler" method="post" action="/crew_account.php">
    <input type="hidden" name="userid" id="userIdHidden">
    <input type="hidden" name="schedule_json" id="scheduleJsonHidden">

    <div class="popup layer pop-set-scheduler sched-popup">
        <div class="pop-head">
            <p class="title">Suspension Setup</p>
            <button type="button" class="sched-close" onclick="popClose('pop-set-scheduler')">×</button>
        </div>

        <div class="pop-cont sched-modal-body">
            <div class="sched-setup-wrap">
                <table class="sched-setup-table">
                    <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:50px">ACT</th>
                        <th style="width:180px">FROM</th>
                        <th style="width:30px"></th>
                        <th style="width:180px">TO</th>
                        <th style="width:170px">DAY</th>
                    </tr>
                    </thead>
                    <tbody id="sched-body"></tbody>
                </table>
            </div>
        </div>

        <div class="pop-foot sched-modal-footer">
            <button type="button" class="btn md fill-mint" onclick="submit_crewscheduler()">APPLY</button>
            <button type="button" class="btn md fill-dark" onclick="popClose('pop-set-scheduler')">CANCEL</button>
        </div>
    </div>
</form>



</body>
<script type="text/javascript">
    (function () {
        const tbody = document.getElementById('sched-body');
        const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        function buildOptions(max) {
            let html = '';
            for (let i = 0; i <= max; i++) {
                const v = String(i).padStart(2, '0');
                html += `<option value="${v}">${v}</option>`;
            }
            return html;
        }

        function timeSelect(name, max) {
            return `
                <select class="sched-time-select" name="${name}">
                    ${buildOptions(max)}
                </select>
            `;
        }

        function dayButtons(rowIndex) {
            return `
                <div class="sched-days">
                    ${days.map(day => `
                        <button type="button"
                                class="sched-day"
                                data-row="${rowIndex}"
                                data-day="${day}">
                            ${day}
                        </button>
                    `).join('')}
                </div>
            `;
        }

        let rowsHtml = '';
        for (let i = 1; i <= 3; i++) {
            rowsHtml += `
                <tr>
                    <td><span class="sched-no-badge">${i}</span></td>
                    <td>
                        <input type="checkbox" class="sched-act-check" name="act_${i}">
                    </td>
                    <td>
                        <div class="sched-time-group">
                            ${timeSelect(`from_h_${i}`, 23)}
                            <span class="sched-time-colon">:</span>
                            ${timeSelect(`from_m_${i}`, 59)}
                        </div>
                    </td>
                    <td><span class="sched-arrow">→</span></td>
                    <td>
                        <div class="sched-time-group">
                            ${timeSelect(`to_h_${i}`, 23)}
                            <span class="sched-time-colon">:</span>
                            ${timeSelect(`to_m_${i}`, 59)}
                        </div>
                    </td>
                    <td>${dayButtons(i)}</td>
                </tr>
            `;
        }
        tbody.innerHTML = rowsHtml;

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('sched-day')) {
                e.target.classList.toggle('active');
            }
        });
    })();

    function submit_crewscheduler() {
        const rows = [];
        const tbodyRows = document.querySelectorAll('#sched-body tr');

        tbodyRows.forEach((tr, idx) => {
            const rowNo = idx + 1;
            const active = tr.querySelector(`input[name="act_${rowNo}"]`).checked;
            const from_h = tr.querySelector(`select[name="from_h_${rowNo}"]`).value;
            const from_m = tr.querySelector(`select[name="from_m_${rowNo}"]`).value;
            const to_h = tr.querySelector(`select[name="to_h_${rowNo}"]`).value;
            const to_m = tr.querySelector(`select[name="to_m_${rowNo}"]`).value;

            const selectedDays = Array.from(tr.querySelectorAll('.sched-day.active'))
                .map(btn => btn.dataset.day);

            rows.push({
                row: rowNo,
                active: active ? 1 : 0,
                from: `${from_h}:${from_m}`,
                to: `${to_h}:${to_m}`,
                days: selectedDays
            });
        });

        document.getElementById('scheduleJsonHidden').value = JSON.stringify(rows);
        document.getElementById('crewscheduler').submit();
    }
    function confirm_exportCsv() {
        window.location.href = "crew_account.php?export=csv";
    }
    function refreshValue() {
        $.ajax({
            url: "./crew_account.php",
            data: {data_update: "true"},
            type: 'POST',
            dataType: 'json',
            success: function (result) {
                $("#crew_account_table").html(result.crew_wifi_table);
            },
            error: function (request, status, error) {
                alert(error);
            }
        })
    }
    //setInterval(refreshValue, 60000); // 밀리초 단위이므로 5초는 5000밀리초
    function submit_registerusers(){
        popClose('pop-set-manage');
        $('#registerusers').submit();
    }
    function submit_modifyusers() {
        if (!confirm("Selected users are being set this configure, OK to continue.")) return;

        // 1) modifyusers 폼 데이터 → __csrf_magic 포함됨
        let data = $("#modifyusers").serialize();   // 여기 안에 __csrf_magic 있어야 함

        // 2) PHP에서 트리거로 쓰는 플래그 이름은 modifyusers (s 붙음)
        data += "&modifyusers=true";

        // 3) 체크된 userlist 추가
        let userlist = $('input[name="userlist[]"]:checked')
            .map(function () { return $(this).val(); })
            .get();

        for (let i = 0; i < userlist.length; i++) {
            data += "&userlist[]=" + encodeURIComponent(userlist[i]);
        }

        // (선택) modifydata 로 폼 내용 통째로 넘기고 싶으면:
        data += "&modifydata=" + encodeURIComponent($("#modifyusers").serialize());

        $.ajax({
            url: "crew_account.php",   // 스킴/호스트 동일하게, ./ 도 가능
            type: "POST",
            data: data,                // ★ 그냥 문자열
            // processData / contentType 기본값 유지 (건들지 말기)
            success: function (result) {
                location.replace("crew_account.php");
            }
        });

        popClose('pop-modify-manage');
    }

    function confirm_resetPw(){
        if(window.confirm('Selected user passwords will be reset to 1111, OK to continue.')){
            $.ajax({
                url: "./crew_account.php",
                data: {resetpw: "true", userlist: $('input[name="userlist[]"]:checked').map(function(){return $(this).val();}).get()},
                type: 'POST',
                success: function (result) {
                    location.replace("crew_account.php");
                },
                error: function (result) {
                }
            })
        }
        else { return false; }
    }
    function confirm_setRandomPw(){
        if(window.confirm('Selected users password would be set to random 6 digits, OK to continue.')){
            $.ajax({
                url: "./crew_account.php",
                data: {setrandompw: "true", userlist: $('input[name="userlist[]"]:checked').map(function(){return $(this).val();}).get()},
                type: 'POST',
                success: function (result) {
                    location.replace("crew_account.php");
                },
                error: function (result) {
                }
            })
        }
        else { return false; }
    }
    function confirm_resetData(){
        if(window.confirm(`Selected user data usage will be reset, OK to continue.`)){
            $.ajax({
                url: "./crew_account.php",
                data: {resetdata: "true", userlist: $('input[name="userlist[]"]:checked').map(function(){return $(this).val();}).get()},
                type: 'POST',
                success: function (result) {
                    location.replace("crew_account.php");
                },
                error: function (result) {
                }
            })
        }
        else { return false; }
    }
    function confirm_delUser(){
        if(window.confirm(`Selected user IDs are being deleted, OK to continue.`)){
            $.ajax({
                url: "./crew_account.php",
                data: {deluser: "true", userlist: $('input[name="userlist[]"]:checked').map(function () {return $(this).val();}).get()},
                type: 'POST',
                success: function (result) {
                    location.replace("crew_account.php");
                },
                error: function (result) {}
            })
        }
    }
    function confirm_checkPw(){
        var pwlist = "<?php echo check_wifi_account_password(); ?>".split("|||");
        var idlist = "<?php echo check_wifi_account_id(); ?>".split("|||");
        var result="";
        var resultlist = document.getElementsByName('userlist[]');
        for(let idcount=0; idcount<resultlist.length; idcount++){
            if(resultlist[idcount].checked){
                for(let idlistcount=0; idlistcount<idlist.length; idlistcount++){
                    if(resultlist[idcount].value===idlist[idlistcount]){
                        result += "\n" + resultlist[idcount].value + " : " + pwlist[idlistcount]+"<br>";
                    }
                }
            }
        }
        if(result===''){
            document.getElementById('message_text').innerHTML = "Please select a user";
            return popOpenAndDim("pop-message",true);
        }
        else{
            document.getElementById('message_text').innerHTML = result;
            return popOpenAndDim("pop-message",true);
        }
    }
    function selectAll(selectAll)  {
        const checkboxes = document.getElementsByName('userlist[]');
        checkboxes.forEach((checkbox) => {checkbox.checked = selectAll.checked;})
    }

    (function () {
        function bindPositiveIntOnly(id, allowEmpty) {
            const el = document.getElementById(id);
            if (!el) return;

            el.addEventListener('input', function () {
                let v = el.value;
                v = v.replace(/\D+/g, '');  // 숫자만 남김
                v = v.replace(/^0+/, '');   // 선행 0 제거 -> 0 방지
                el.value = v;
            });

            el.form?.addEventListener('submit', function (e) {
                const v = el.value.trim();
                if (allowEmpty) {
                    if (v && !/^[1-9]\d*$/.test(v)) {
                        e.preventDefault();
                        alert(id + '는 1 이상의 정수만 입력 가능합니다.');
                        el.focus();
                    }
                } else {
                    if (!/^[1-9]\d*$/.test(v)) {
                        e.preventDefault();
                        alert(id + '는 1 이상의 정수만 입력 가능합니다.');
                        el.focus();
                    }
                }
            });
        }

        // 필요에 맞게 allowEmpty 조절 가능
        bindPositiveIntOnly('datalimit', false);
        bindPositiveIntOnly('timelimit', true);   // 미구현이면 비워두기 허용 추천
        bindPositiveIntOnly('downspeed', true);   // 실험 기능이면 비워두기 허용 추천
        bindPositiveIntOnly('upspeed', true);
        bindPositiveIntOnly('dataamount', false);
        bindPositiveIntOnly('vouchernumber', false);
    })();

</script>
</html>