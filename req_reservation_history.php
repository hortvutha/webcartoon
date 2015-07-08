<?php

if (!isset($_SESSION)) {
    session_start();
} //Start the session
include("../connect_db.php");

include 'seed_encode/class.crypto.php';
$crypto = new Crypto();

// Include and instantiate the class.
require_once '../Mobile_Detect.php';
$detect = new Mobile_Detect;

//date_default_timezone_set('Europe/London'); 

if (!isset($_SESSION['adminid']) and ! isset($_SESSION['userid'])) {
    echo 'no_authentication';
    exit;
} else {
 
    $cmd = post("cmd");
    $encryptKey = singleCell_qry("settingValue", "tblgeneralsetting", "settingName='encryption_key' LIMIT 1");
    if (isset($_SESSION['adminid'])) {
        $sessonid = $_SESSION['adminid'];
        $company_sessionid = 0;
        $verifyRequest = authenticatedRequest($sessonid, $cmd);
        if (!$verifyRequest) {
            echo 'no_authentication';
            exit;
        }
    } elseif (isset($_SESSION['userid'])) {
        $sessonid = 0;
        $company_sessionid = decodeString($_SESSION['userid'], $encryptKey);
        //better set all the allowed request made by company account
    }
}

if ($cmd == 'updateGeneralSettings_inup') {
    $settingData = $_POST['settingData'];
    $datetime = date("Y-m-d H:i:s");
    foreach ($settingData as $key => $value) {
        exec_query_utf8("UPDATE tblgeneralsetting SET settingValue='$value',userid=" . $sessonid . ",datetime='$datetime' WHERE settingName='$key' AND active=1 LIMIT 1");
    }
} elseif ($cmd == 'newBlockedWord_block') {
    $keyword = post('keyword');
    $datetime = date("Y-m-d H:i:s");
    if ($keyword <> '') {
        if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblkeyword WHERE keyword='$keyword'")) == 0) {
            exec_query_utf8("INSERT INTO tblkeyword SET keyword='$keyword',type=1,userid=" . $sessonid . ",datetime='$datetime',blocked=1");
        } else {
            exec_query_utf8("UPDATE tblkeyword SET blocked=1,active=1,userid=" . $sessonid . ",datetime='$datetime' WHERE keyword='$keyword' LIMIT 1");
            echo 'exist';
        }
    } else {
        echo '0';
    }
} elseif ($cmd == 'keywordList') {
    $listType = post("listType");
    $keyword = post("keyword");
    $currentPage = intval(post("currentPage"));
    $rowsPerPage = post("rowsPerPage");
    $navAction = post("navAction");

    $sql_condition = 'WHERE';
    if ($listType == 'block') {
        $sql_condition.=' blocked=1';
    }
    if ($keyword <> '') {
        if ($sql_condition == 'WHERE') {
            $sql_condition.=" (keyword LIKE '%$keyword%' OR datetime LIKE '%$keyword%')";
        } else {
            $sql_condition.=" AND (keyword LIKE '%$keyword%' OR datetime LIKE '%$keyword%')";
        }
    }
    if ($sql_condition == 'WHERE') {
        $sql_condition = '';
    }

    //work with total page
    $navRow_qry = exec_query_utf8("SELECT * FROM tblkeyword $sql_condition ORDER BY id DESC");
    $totalRow = mysqli_num_rows($navRow_qry);
    $totalPages = $totalRow / $rowsPerPage;
    if ($totalRow % $rowsPerPage > 0) {
        $totalPages = intval($totalPages) + 1;
    }

    //get the target page number	
    $targetPage = 1;
    $nav_btn_disable = array();
    if ($navAction == 'first') {
        $targetPage = 1;
    } elseif ($navAction == 'prev') {
        $targetPage = $currentPage - 1;
    } elseif ($navAction == 'next') {
        $targetPage = $currentPage + 1;
    } elseif ($navAction == 'last') {
        $targetPage = $totalPages;
    } elseif ($navAction == 'goto') {
        $targetPage = $currentPage;
    }
    //get goto select list
    $gotoSelectNum = array();
    for ($i = 1; $i <= $totalPages; $i++) {
        $gotoSelectNum[] = $i;
    }

    if ($totalPages == 1) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 0, 'nav_last' => 0);
    } elseif ($targetPage == 1) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 1, 'nav_last' => 1);
    } elseif ($targetPage == $totalPages) {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 0, 'nav_last' => 0);
    } else {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 1, 'nav_last' => 1);
    }

    $startIndex = ($targetPage - 1) * $rowsPerPage;
    $keywordListString = '';
    $i = $startIndex + 1;
    $keyword_qry = exec_query_utf8("SELECT * FROM tblkeyword $sql_condition ORDER BY id DESC LIMIT " . $startIndex . ",$rowsPerPage");
    while ($keyword_row = mysqli_fetch_assoc($keyword_qry)) {
        $adminName = '';
        $user_qry = exec_query_utf8("SELECT firstName FROM tbluser WHERE id=" . $keyword_row['userid'] . " LIMIT 1");
        while ($user_row = mysqli_fetch_assoc($user_qry)) {
            $adminName = $user_row['firstName'];
        }
        $keywordListString .= '<tr>
									<td>' . $i . '</td>                                    
                                    <td>' . $keyword_row['keyword'] . '</td>
                                    <td>' . $keyword_row['datetime'] . '</td>
                                    <td>' . $adminName . '</td>
									<td style="text-align:center;"><button class="btn btn-primary btn-xs" onclick="confirmUnblock(\'' . $keyword_row['id'] . '\')">Unblock</button></td>
                               </tr>';
        $i++;
    }
    if ($keywordListString == '') {
        $keywordListString = '<tr><td colspan="5" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> No Keyword Found</td></tr>';
    }
    $data = array('list' => $keywordListString, 'targetPage' => $targetPage, 'totalPages' => $totalPages, 'gotoSelectNum' => $gotoSelectNum, 'nav_btn_disable' => $nav_btn_disable);

    echo json_encode($data);
} elseif ($cmd == 'unblockKeyword_block') {
    $id = post("id");
    $datetime = date("Y-m-d H:i:s");
    exec_query_utf8("UPDATE tblkeyword SET blocked=0,userid=" . $sessonid . ",datetime='$datetime' WHERE id='$id' LIMIT 1");
} elseif ($cmd == 'mainLocationList') {
    $keyword = post("keyword");
    $currentPage = intval(post("currentPage"));
    $rowsPerPage = post("rowsPerPage");
    $navAction = post("navAction");

    $sql_condition = 'WHERE';
    if ($keyword <> '') {
        if ($sql_condition == 'WHERE') {
            $sql_condition.=" (cityName LIKE '%$keyword%')";
        } else {
            $sql_condition.=" AND (cityName LIKE '%$keyword%')";
        }
    }
    if ($sql_condition == 'WHERE') {
        $sql_condition = '';
    }
    //work with total page
    $navRow_qry = exec_query_utf8("SELECT * FROM tblmainlocation $sql_condition ORDER BY countryid,cityName ASC");
    $totalRow = mysqli_num_rows($navRow_qry);
    $totalPages = $totalRow / $rowsPerPage;
    if ($totalRow % $rowsPerPage > 0) {
        $totalPages = intval($totalPages) + 1;
    }

    //get the target page number	
    $targetPage = 1;
    $nav_btn_disable = array();
    if ($navAction == 'first') {
        $targetPage = 1;
    } elseif ($navAction == 'prev') {
        $targetPage = $currentPage - 1;
    } elseif ($navAction == 'next') {
        $targetPage = $currentPage + 1;
    } elseif ($navAction == 'last') {
        $targetPage = $totalPages;
    } elseif ($navAction == 'goto') {
        $targetPage = $currentPage;
    }
    //get goto select list
    $gotoSelectNum = array();
    for ($i = 1; $i <= $totalPages; $i++) {
        $gotoSelectNum[] = $i;
    }

    if ($totalPages == 1 or $totalPages == 0) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 0, 'nav_last' => 0);
    } elseif ($targetPage == 1) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 1, 'nav_last' => 1);
    } elseif ($targetPage == $totalPages) {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 0, 'nav_last' => 0);
    } else {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 1, 'nav_last' => 1);
    }

    $startIndex = ($targetPage - 1) * $rowsPerPage;
    $locationListString = '';
    $i = $startIndex + 1;
    $location_qry = exec_query_utf8("SELECT * FROM tblmainlocation $sql_condition ORDER BY countryid,cityName ASC LIMIT " . $startIndex . ",$rowsPerPage");
    while ($location_row = mysqli_fetch_assoc($location_qry)) {
        $countryName = '';
        $country_qry = exec_query_utf8("SELECT shortName FROM tblcountry WHERE id=" . $location_row['countryid'] . " LIMIT 1");
        while ($country_row = mysqli_fetch_assoc($country_qry)) {
            $countryName = $country_row['shortName'];
        }
        //total area
        $totalArea = mysqli_num_rows(exec_query_utf8("SELECT * FROM tblsublocation WHERE cityid=" . $location_row['id']));
        $totalCampSite = mysqli_num_rows(exec_query_utf8("SELECT * FROM tblcamping WHERE locationid IN (SELECT id FROM tblsublocation WHERE cityid=" . $location_row['id'] . ") "));

        $coordiate = explode(',', $location_row['mapCoordinate']);
        $lat = $coordiate['0'];
        $lng = $coordiate['1'];
        $zoom = str_replace('z', '', $coordiate['2']);
        $locationListString .= '<tr>
									<td>' . $i . '</td>                                    
                                    <td>' . $countryName . '</td>
                                    <td>' . $location_row['cityName'] . '</td>
                                    <td>' . $totalArea . '</td>
									<td>' . $totalCampSite . '</td>
									<td>' . $location_row['view'] . '</td>
									<td style="text-align:center;">
										<button class="btn btn-primary btn-xs" onclick="iniEditMainLocation(' . $location_row['id'] . ');"><i class="fa fa-pencil-square-o"></i> Edit</button>
										<button class="btn btn-primary btn-xs" onclick="initialize(' . $lat . ',' . $lng . ',' . $zoom . ',1);"><i class="fa fa-map-marker"></i> Map</button>
									</td>
                               </tr>';
        $i++;
    }
    if ($locationListString == '') {
        $locationListString = '<tr><td colspan="8" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> No Location Found</td></tr>';
    }
    $data = array('list' => $locationListString, 'targetPage' => $targetPage, 'totalPages' => $totalPages, 'gotoSelectNum' => $gotoSelectNum, 'nav_btn_disable' => $nav_btn_disable);

    echo json_encode($data);
} elseif ($cmd == 'newMainLocation_inup') {
    $newMainLocationData = $_POST['newMainLocationData'];
    extract($newMainLocationData);

    if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblmainlocation WHERE countryid='$countryid' AND cityName='$newCityName'")) == 0) {
        $coordinateData = explode(',', $mapCoordinate);
        if (trim($newCityName) <> '' and $countryid > 0 and count(explode(',', $mapCoordinate)) == 3) {
            if (stripos($coordinateData[2], 'z') !== false) {
                $newCoordinateData = $mapCoordinate;
            } else {
                $newCoordinateData = $mapCoordinate . 'z';
            }
            exec_query_utf8("INSERT INTO tblmainlocation SET countryid=$countryid,cityName='$newCityName',mapCoordinate='$newCoordinateData',description='" . addslashes($locationDes) . "'");
        } else {
            echo 'invalid';
        }
    } else {
        echo 'exist';
    }
} elseif ($cmd == 'subLocationList') {
    $keyword = post("keyword");
    $currentPage = intval(post("currentPage"));
    $rowsPerPage = post("rowsPerPage");
    $navAction = post("navAction");

    $sql_condition = 'WHERE';
    if ($keyword <> '') {
        if ($sql_condition == 'WHERE') {
            $sql_condition.=" (subLocationName LIKE '%$keyword%')";
        } else {
            $sql_condition.=" AND (subLocationName LIKE '%$keyword%')";
        }
    }
    if ($sql_condition == 'WHERE') {
        $sql_condition = '';
    }
    //work with total page
    $navRow_qry = exec_query_utf8("SELECT * FROM tblsublocation $sql_condition ORDER BY cityid,subLocationName ASC");
    $totalRow = mysqli_num_rows($navRow_qry);
    $totalPages = $totalRow / $rowsPerPage;
    if ($totalRow % $rowsPerPage > 0) {
        $totalPages = intval($totalPages) + 1;
    }

    //get the target page number	
    $targetPage = 1;
    $nav_btn_disable = array();
    if ($navAction == 'first') {
        $targetPage = 1;
    } elseif ($navAction == 'prev') {
        $targetPage = $currentPage - 1;
    } elseif ($navAction == 'next') {
        $targetPage = $currentPage + 1;
    } elseif ($navAction == 'last') {
        $targetPage = $totalPages;
    } elseif ($navAction == 'goto') {
        $targetPage = $currentPage;
    }
    //get goto select list
    $gotoSelectNum = array();
    for ($i = 1; $i <= $totalPages; $i++) {
        $gotoSelectNum[] = $i;
    }

    if ($totalPages == 1 or $totalPages == 0) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 0, 'nav_last' => 0);
    } elseif ($targetPage == 1) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 1, 'nav_last' => 1);
    } elseif ($targetPage == $totalPages) {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 0, 'nav_last' => 0);
    } else {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 1, 'nav_last' => 1);
    }

    $startIndex = ($targetPage - 1) * $rowsPerPage;
    $locationListString = '';
    $i = $startIndex + 1;
    $location_qry = exec_query_utf8("SELECT * FROM tblsublocation $sql_condition ORDER BY cityid,subLocationName ASC LIMIT " . $startIndex . ",$rowsPerPage");
    while ($location_row = mysqli_fetch_assoc($location_qry)) {
        $cityName = '';
        $countryName = '';
        $city_qry = exec_query_utf8("SELECT countryid,cityName FROM tblmainlocation WHERE id=" . $location_row['cityid'] . " LIMIT 1");
        while ($city_row = mysqli_fetch_assoc($city_qry)) {
            $cityName = $city_row['cityName'];
            $country_qry = exec_query_utf8("SELECT shortName FROM tblcountry WHERE id=" . $city_row['countryid'] . " LIMIT 1");
            while ($country_row = mysqli_fetch_assoc($country_qry)) {
                $countryName = $country_row['shortName'];
            }
        }
        $totalCampSite = mysqli_num_rows(exec_query_utf8("SELECT * FROM tblcamping WHERE locationid=" . $location_row['id']));

        $coordiate = explode(',', $location_row['mapCoordinate']);
        $lat = $coordiate['0'];
        $lng = $coordiate['1'];
        $zoom = str_replace('z', '', $coordiate['2']);
        $locationListString .= '<tr>
									<td>' . $i . '</td>      
									<td>' . $countryName . '</td>                              
                                    <td>' . $cityName . '</td>
                                    <td>' . $location_row['subLocationName'] . '</td>
                                    <td>' . $totalCampSite . '</td>
									<td>' . $location_row['view'] . '</td>
									<td style="text-align:center;">
										<button class="btn btn-primary btn-xs" onclick="iniEditSubLocation(' . $location_row['id'] . ');"><i class="fa fa-pencil-square-o"></i> Edit</button>
										<button class="btn btn-primary btn-xs" onclick="initialize(' . $lat . ',' . $lng . ',' . $zoom . ',1);"><i class="fa fa-map-marker"></i> Map</button>
									</td>
                               </tr>';
        $i++;
    }
    if ($locationListString == '') {
        $locationListString = '<tr><td colspan="8" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> No Location Found</td></tr>';
    }
    $data = array('list' => $locationListString, 'targetPage' => $targetPage, 'totalPages' => $totalPages, 'gotoSelectNum' => $gotoSelectNum, 'nav_btn_disable' => $nav_btn_disable);

    echo json_encode($data);
} elseif ($cmd == 'newSubLocation_inup') {
    $newSubLocationData = $_POST['newSubLocationData'];
    extract($newSubLocationData);

    if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblsublocation WHERE cityid='$cityid' AND subLocationName='$newAreaName'")) == 0) {
        $coordinateData = explode(',', $mapCoordinate);
        if (trim($newAreaName) <> '' and $cityid > 0 and count(explode(',', $mapCoordinate)) == 3) {
            if (stripos($coordinateData[2], 'z') !== false) {
                $newCoordinateData = $mapCoordinate;
            } else {
                $newCoordinateData = $mapCoordinate . 'z';
            }
            exec_query_utf8("INSERT INTO tblsublocation SET cityid=$cityid,subLocationName='$newAreaName',mapCoordinate='$newCoordinateData',description='" . addslashes($locationDes) . "'");
        } else {
            echo 'invalid';
        }
    } else {
        echo 'exist';
    }
} elseif ($cmd == 'iniEditMainLocation') {
    $id = post('id');

    $data = array();
    $location_qry = exec_query_utf8("SELECT * FROM tblmainlocation WHERE id=$id LIMIT 1");
    while ($location_row = mysqli_fetch_assoc($location_qry)) {
        $data = array('cityName' => $location_row['cityName'], 'countryid' => $location_row['countryid'], 'coordinate' => $location_row['mapCoordinate'], 'des' => $location_row['description']);
    }
    echo json_encode($data);
} elseif ($cmd == 'iniEditSubLocation') {
    $id = post('id');

    $data = array();
    $location_qry = exec_query_utf8("SELECT * FROM tblsublocation WHERE id=$id LIMIT 1");
    while ($location_row = mysqli_fetch_assoc($location_qry)) {
        $data = array('areaName' => $location_row['subLocationName'], 'cityid' => $location_row['cityid'], 'coordinate' => $location_row['mapCoordinate'], 'des' => $location_row['description']);
    }
    echo json_encode($data);
} elseif ($cmd == 'updateMainLocation_inup') {
    $id = post('id');
    $editSubLocationData = $_POST['editSubLocationData'];
    extract($editSubLocationData);

    if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblmainlocation WHERE countryid='$editCountryid' AND cityName='$editCityName' AND id<>$id")) == 0) {
        $coordinateData = explode(',', $editMapCoordinate);
        if (trim($editCityName) <> '' and $editCountryid > 0 and count(explode(',', $editMapCoordinate)) == 3) {
            if (stripos($coordinateData[2], 'z') !== false) {
                $newCoordinateData = $editMapCoordinate;
            } else {
                $newCoordinateData = $editMapCoordinate . 'z';
            }
            exec_query_utf8("UPDATE tblmainlocation SET countryid=$editCountryid,cityName='$editCityName',mapCoordinate='$editMapCoordinate',description='" . addslashes($editLocationDes) . "' WHERE id=$id LIMIT 1");
        } else {
            echo 'invalid';
        }
    } else {
        echo 'exist';
    }
} elseif ($cmd == 'updateSubLocation_inup') {
    $id = post('id');
    $editSubLocationData = $_POST['editSubLocationData'];
    extract($editSubLocationData);

    if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblsublocation WHERE cityid='$editCityid' AND subLocationName='$editAreaName' AND id<>$id")) == 0) {
        $coordinateData = explode(',', $editMapCoordinate);
        if (trim($editAreaName) <> '' and $editCityid > 0 and count(explode(',', $editMapCoordinate)) == 3) {
            if (stripos($coordinateData[2], 'z') !== false) {
                $newCoordinateData = $editMapCoordinate;
            } else {
                $newCoordinateData = $editMapCoordinate . 'z';
            }
            exec_query_utf8("UPDATE tblsublocation SET cityid=$editCityid,subLocationName='$editAreaName',mapCoordinate='$editMapCoordinate',description='" . addslashes($editLocationDes) . "' WHERE id=$id LIMIT 1");
        } else {
            echo 'invalid';
        }
    } else {
        echo 'exist';
    }
} elseif ($cmd == 'categoryList') {
    $keyword = post("keyword");
    $cateTypeid = post('cateTypeid');
    $currentPage = intval(post("currentPage"));
    $rowsPerPage = post("rowsPerPage");
    $navAction = post("navAction");

    $sql_condition = 'AND';
    if ($keyword <> '') {
        $sql_condition.=" categoryTitle LIKE '%$keyword%'";
    }
    if ($sql_condition == 'AND') {
        $sql_condition = '';
    }
    //work with total page
    $navRow_qry = exec_query_utf8("SELECT * FROM tblcategory WHERE categoryType=$cateTypeid $sql_condition ORDER BY categoryTitle ASC");
    $totalRow = mysqli_num_rows($navRow_qry);
    $totalPages = $totalRow / $rowsPerPage;
    if ($totalRow % $rowsPerPage > 0) {
        $totalPages = intval($totalPages) + 1;
    }

    //get the target page number	
    $targetPage = 1;
    $nav_btn_disable = array();
    if ($navAction == 'first') {
        $targetPage = 1;
    } elseif ($navAction == 'prev') {
        $targetPage = $currentPage - 1;
    } elseif ($navAction == 'next') {
        $targetPage = $currentPage + 1;
    } elseif ($navAction == 'last') {
        $targetPage = $totalPages;
    } elseif ($navAction == 'goto') {
        $targetPage = $currentPage;
    }
    //get goto select list
    $gotoSelectNum = array();
    for ($i = 1; $i <= $totalPages; $i++) {
        $gotoSelectNum[] = $i;
    }

    if ($totalPages == 1 or $totalPages == 0) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 0, 'nav_last' => 0);
    } elseif ($targetPage == 1) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 1, 'nav_last' => 1);
    } elseif ($targetPage == $totalPages) {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 0, 'nav_last' => 0);
    } else {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 1, 'nav_last' => 1);
    }

    $startIndex = ($targetPage - 1) * $rowsPerPage;
    $categoryListString = '';
    $i = $startIndex + 1;
    $category_qry = exec_query_utf8("SELECT * FROM tblcategory WHERE categoryType=$cateTypeid $sql_condition ORDER BY categoryTitle ASC LIMIT " . $startIndex . ",$rowsPerPage");
    while ($category_row = mysqli_fetch_assoc($category_qry)) {
        $active = $category_row['active'];
        $categoryListString .= '<tr>
									<td class="tableCellCenter">' . $i . '</td>                                    
                                    <td class="tableCellCenter">' . $category_row['categoryTitle'] . '</td>
                                    <td>' . substr_unicode(strip_tags($category_row['description']), 0, 300) . '...</td>
                                    <td class="tableCellCenter">' . $category_row['icon'] . '</td>
									<td>' . $category_row['datetime'] . '</td>
									<td class="tableCellCenter action_td_verticle">
										<div><button class="btn btn-primary btn-xs" onclick="iniEditCateItem(' . $category_row['id'] . ');"><i class="fa fa-pencil-square-o"></i> Edit</button></div>
										<div><button type="submit" class="btn btn-primary btn-xs" style="' . ($active == 0 ? 'background:#ec6513;' : '') . '" onclick="comfirmCateItemDisable(' . $category_row['id'] . ',' . $active . ')" >' . ($active == 1 ? '<i class="fa fa-lock"></i> Deactivate' : '<i class="fa fa-unlock-alt"></i> Activate') . '</button></div>
									</td>
                               </tr>';
        $i++;
    }
    if ($categoryListString == '') {
        $categoryListString = '<tr><td colspan="6" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> No Category Found</td></tr>';
    }
    $data = array('list' => $categoryListString, 'targetPage' => $targetPage, 'totalPages' => $totalPages, 'gotoSelectNum' => $gotoSelectNum, 'nav_btn_disable' => $nav_btn_disable);

    echo json_encode($data);
} elseif ($cmd == 'disableCateItem_block') {
    $id = post('id');

    $camp_qry = exec_query_utf8("UPDATE tblcategory SET active = NOT active WHERE id=$id LIMIT 1");
} elseif ($cmd == 'newCateItem_inup') {
    $newCateItemData = $_POST['newCateItemData'];
    extract($newCateItemData);
    $datetime = date("Y-m-d H:i:s");

    if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcategory WHERE categoryType=$newcateTypeid AND categoryTitle='$newItemName'")) == 0) {
        if (trim($newItemName) <> '' and $newcateTypeid > 0 and trim($newItemCode) <> '') {
            exec_query_utf8("INSERT INTO tblcategory SET categoryType=$newcateTypeid,code='$newItemCode',categoryTitle='$newItemName',icon='$newIcon',description='" . addslashes($newItemDes) . "',datetime='$datetime'");
        } else {
            echo 'invalid';
        }
    } else {
        echo 'exist';
    }
} elseif ($cmd == 'iniEditCateItem') {
    $id = post('id');

    $data = array();
    $cateItem_qry = exec_query_utf8("SELECT * FROM tblcategory WHERE id=$id LIMIT 1");
    while ($cateItem_row = mysqli_fetch_assoc($cateItem_qry)) {
        $data = array('itemName' => $cateItem_row['categoryTitle'], 'cateid' => $cateItem_row['categoryType'], 'code' => $cateItem_row['code'], 'icon' => $cateItem_row['icon'], 'des' => $cateItem_row['description']);
    }
    echo json_encode($data);
} elseif ($cmd == 'updateCateItem_inup') {
    $id = post('id');
    $editCateItemData = $_POST['editCateItemData'];
    extract($editCateItemData);
    $datetime = date("Y-m-d H:i:s");

    if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcategory WHERE categoryType='$editCateTypeid' AND categoryTitle='$editItemName' AND id<>$id")) == 0) {
        if (trim($editItemName) <> '' and $editCateTypeid > 0) {
            exec_query_utf8("UPDATE tblcategory SET categoryType=$editCateTypeid,categoryTitle='$editItemName',icon='$editIcon',description='" . addslashes($editItemDes) . "',datetime='$datetime' WHERE id=$id LIMIT 1");
        } else {
            echo 'invalid';
        }
    } else {
        echo 'exist';
    }
} elseif ($cmd == 'categoryTypeList') {
    $keyword = post("keyword");
    $currentPage = intval(post("currentPage"));
    $rowsPerPage = post("rowsPerPage");
    $navAction = post("navAction");

    $sql_condition = 'WHERE';
    if ($keyword <> '') {
        if ($sql_condition == 'WHERE') {
            $sql_condition.=" typeName LIKE '%$keyword%'";
        } else {
            $sql_condition.=" AND typeName LIKE '%$keyword%'";
        }
    }
    if ($sql_condition == 'WHERE') {
        $sql_condition = '';
    }
    //work with total page
    $navRow_qry = exec_query_utf8("SELECT * FROM tblcategorytype $sql_condition ORDER BY typeName ASC");
    $totalRow = mysqli_num_rows($navRow_qry);
    $totalPages = $totalRow / $rowsPerPage;
    if ($totalRow % $rowsPerPage > 0) {
        $totalPages = intval($totalPages) + 1;
    }

    //get the target page number	
    $targetPage = 1;
    $nav_btn_disable = array();
    if ($navAction == 'first') {
        $targetPage = 1;
    } elseif ($navAction == 'prev') {
        $targetPage = $currentPage - 1;
    } elseif ($navAction == 'next') {
        $targetPage = $currentPage + 1;
    } elseif ($navAction == 'last') {
        $targetPage = $totalPages;
    } elseif ($navAction == 'goto') {
        $targetPage = $currentPage;
    }
    //get goto select list
    $gotoSelectNum = array();
    for ($i = 1; $i <= $totalPages; $i++) {
        $gotoSelectNum[] = $i;
    }

    if ($totalPages == 1 or $totalPages == 0) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 0, 'nav_last' => 0);
    } elseif ($targetPage == 1) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 1, 'nav_last' => 1);
    } elseif ($targetPage == $totalPages) {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 0, 'nav_last' => 0);
    } else {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 1, 'nav_last' => 1);
    }

    $startIndex = ($targetPage - 1) * $rowsPerPage;
    $categoryListString = '';
    $i = $startIndex + 1;
    $category_qry = exec_query_utf8("SELECT * FROM tblcategorytype $sql_condition ORDER BY typeName ASC LIMIT " . $startIndex . ",$rowsPerPage");
    while ($category_row = mysqli_fetch_assoc($category_qry)) {
        $visible = $category_row['visible'];
        $categoryListString .= '<tr>
									<td>' . $i . '</td>                                    
                                    <td>' . $category_row['typeName'] . '</td>
                                    <td>' . $category_row['description'] . '</td>
                                    <td class="tableCellCenter">' . $category_row['icon'] . '</td>
									<td>' . $category_row['datetime'] . '</td>
									<td style="text-align:center;">
										<button class="btn btn-primary btn-xs" onclick="iniEditCateType(' . $category_row['id'] . ');"><i class="fa fa-pencil-square-o"></i> Edit</button>
										<button class="btn btn-primary btn-xs" style="' . ($visible == 1 ? '' : 'background:#d72854;') . '" onclick="comfirmCateTypeVisibility(' . $category_row['id'] . ',' . $visible . ');"><i class="fa ' . ($visible == 1 ? 'fa-eye' : 'fa-eye-slash') . ' fa-fw"></i></button>
										<a href="/admin/category?c=' . $category_row['id'] . '"><button class="btn btn-primary btn-xs"><i class="fa fa-list-ul fa-fw"></i></button></a>
									</td>
                               </tr>';
        $i++;
    }
    if ($categoryListString == '') {
        $categoryListString = '<tr><td colspan="6" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> No Category Type Found</td></tr>';
    }
    $data = array('list' => $categoryListString, 'targetPage' => $targetPage, 'totalPages' => $totalPages, 'gotoSelectNum' => $gotoSelectNum, 'nav_btn_disable' => $nav_btn_disable);

    echo json_encode($data);
} elseif ($cmd == 'setCateTypeVisibility_block') {
    $id = post('id');

    $camp_qry = exec_query_utf8("UPDATE tblcategorytype SET visible = NOT visible WHERE id=$id LIMIT 1");
} elseif ($cmd == 'newCateType_inup') {
    $newCateTypeData = $_POST['newCateTypeData'];
    extract($newCateTypeData);
    $datetime = date("Y-m-d H:i:s");

    if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcategorytype WHERE typeName='$newCateTypeName'")) == 0) {
        if (trim($newCateTypeName) <> '') {
            exec_query_utf8("INSERT INTO tblcategorytype SET typeName='$newCateTypeName',icon='$newIcon',description='" . addslashes($newCateTypeDes) . "',datetime='$datetime'");
        } else {
            echo 'invalid';
        }
    } else {
        echo 'exist';
    }
} elseif ($cmd == 'iniEditCateType') {
    $id = post('id');

    $data = array();
    $cateItem_qry = exec_query_utf8("SELECT * FROM tblcategorytype WHERE id=$id LIMIT 1");
    while ($cateItem_row = mysqli_fetch_assoc($cateItem_qry)) {
        $data = array('typeName' => $cateItem_row['typeName'], 'code' => $cateItem_row['typeCode'], 'icon' => $cateItem_row['icon'], 'des' => $cateItem_row['description']);
    }
    echo json_encode($data);
} elseif ($cmd == 'updateCateType_inup') {
    $id = post('id');
    $editCateTypeData = $_POST['editCateTypeData'];
    extract($editCateTypeData);
    $datetime = date("Y-m-d H:i:s");

    if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcategorytype WHERE typeName='$editCateTypeName' AND id<>$id")) == 0) {
        if (trim($editCateTypeName) <> '') {
            exec_query_utf8("UPDATE tblcategorytype SET typeName='$editCateTypeName',icon='$editIcon',description='" . addslashes($editCateTypeDes) . "',datetime='$datetime' WHERE id=$id LIMIT 1");
        } else {
            echo 'invalid';
        }
    } else {
        echo 'exist';
    }
} elseif ($cmd == 'activityList') {
    $keyword = post("keyword");
    $activityCateid = post('activityCateid');
    $currentPage = intval(post("currentPage"));
    $rowsPerPage = post("rowsPerPage");
    $navAction = post("navAction");

    $sql_condition = 'WHERE';
    if ($keyword <> '') {
        $sql_condition.=" (description LIKE '%$keyword%' OR userid IN (SELECT id FROM tbluser WHERE firstName LIKE '%$keyword%' OR lastName LIKE '%$keyword%'))";
    }
    if ($activityCateid > 0) {
        $sql_condition.= ($sql_condition == 'WHERE' ? '' : ' AND') . " logCategory=$activityCateid";
    }
    if ($sql_condition == 'WHERE') {
        $sql_condition = '';
    }
    //work with total page
    $navRow_qry = exec_query_utf8("SELECT * FROM tbluserlog $sql_condition ORDER BY datetime DESC");
    $totalRow = mysqli_num_rows($navRow_qry);
    $totalPages = $totalRow / $rowsPerPage;
    if ($totalRow % $rowsPerPage > 0) {
        $totalPages = intval($totalPages) + 1;
    }

    //get the target page number	
    $targetPage = 1;
    $nav_btn_disable = array();
    if ($navAction == 'first') {
        $targetPage = 1;
    } elseif ($navAction == 'prev') {
        $targetPage = $currentPage - 1;
    } elseif ($navAction == 'next') {
        $targetPage = $currentPage + 1;
    } elseif ($navAction == 'last') {
        $targetPage = $totalPages;
    } elseif ($navAction == 'goto') {
        $targetPage = $currentPage;
    }
    //get goto select list
    $gotoSelectNum = array();
    for ($i = 1; $i <= $totalPages; $i++) {
        $gotoSelectNum[] = $i;
    }

    if ($totalPages == 1 or $totalPages == 0) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 0, 'nav_last' => 0);
    } elseif ($targetPage == 1) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 1, 'nav_last' => 1);
    } elseif ($targetPage == $totalPages) {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 0, 'nav_last' => 0);
    } else {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 1, 'nav_last' => 1);
    }

    $startIndex = ($targetPage - 1) * $rowsPerPage;
    $activityListString = '';
    $i = $startIndex + 1;
    $activity_qry = exec_query_utf8("SELECT * FROM tbluserlog $sql_condition ORDER BY datetime DESC LIMIT " . $startIndex . ",$rowsPerPage");
    while ($activity_row = mysqli_fetch_assoc($activity_qry)) {


        $activityListString .= '<tr>
									<td class="tableCellCenter">' . $i . '</td>
                                    <td>' . singleCell_qry('firstName', 'tbluser', 'id=' . $activity_row['userid'] . ' AND active=1') . '</td>
                                    <td>' . singleCell_qry('categoryTitle', 'tblcategory', 'id=' . $activity_row['logCategory'] . ' AND active=1') . '</td>
                                    <td>' . $activity_row['description'] . '</td>
									<td>' . $activity_row['ip'] . '</td>
									<td>' . $activity_row['isp'] . '</td>
									<td class="tableCellCenter">' . date('Y-m-d H:i A', strtotime($activity_row['datetime'])) . '</td>
                               </tr>';
        $i++;
    }
    if ($activityListString == '') {
        $activityListString = '<tr><td colspan="7" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> No Activity Found</td></tr>';
    }
    $data = array('list' => $activityListString, 'targetPage' => $targetPage, 'totalPages' => $totalPages, 'gotoSelectNum' => $gotoSelectNum, 'nav_btn_disable' => $nav_btn_disable);

    echo json_encode($data);
} elseif ($cmd == 'memberList') {
    $keyword = post("keyword");
    $memberCateid = post('memberCateid');
    $currentPage = intval(post("currentPage"));
    $rowsPerPage = post("rowsPerPage");
    $navAction = post("navAction");

    $sql_condition = 'AND';
    if ($keyword <> '') {
        $sql_condition.=" (firstName LIKE '%$keyword%' OR lastName LIKE '%$keyword%')";
    }
    if ($sql_condition == 'AND') {
        $sql_condition = '';
    }
    //work with total page
    $navRow_qry = exec_query_utf8("SELECT * FROM tbluser WHERE userTypeid=$memberCateid $sql_condition ORDER BY registerDate DESC");
    $totalRow = mysqli_num_rows($navRow_qry);
    $totalPages = $totalRow / $rowsPerPage;
    if ($totalRow % $rowsPerPage > 0) {
        $totalPages = intval($totalPages) + 1;
    }

    //get the target page number	
    $targetPage = 1;
    $nav_btn_disable = array();
    if ($navAction == 'first') {
        $targetPage = 1;
    } elseif ($navAction == 'prev') {
        $targetPage = $currentPage - 1;
    } elseif ($navAction == 'next') {
        $targetPage = $currentPage + 1;
    } elseif ($navAction == 'last') {
        $targetPage = $totalPages;
    } elseif ($navAction == 'goto') {
        $targetPage = $currentPage;
    }
    //get goto select list
    $gotoSelectNum = array();
    for ($i = 1; $i <= $totalPages; $i++) {
        $gotoSelectNum[] = $i;
    }

    if ($totalPages == 1 or $totalPages == 0) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 0, 'nav_last' => 0);
    } elseif ($targetPage == 1) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 1, 'nav_last' => 1);
    } elseif ($targetPage == $totalPages) {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 0, 'nav_last' => 0);
    } else {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 1, 'nav_last' => 1);
    }

    $startIndex = ($targetPage - 1) * $rowsPerPage;
    $memberListString = '';
    $i = $startIndex + 1;
    $member_qry = exec_query_utf8("SELECT * FROM tbluser WHERE userTypeid=$memberCateid $sql_condition ORDER BY registerDate DESC LIMIT " . $startIndex . ",$rowsPerPage");
    while ($member_row = mysqli_fetch_assoc($member_qry)) {
        $blocked = $member_row['blocked'];
        $memberListString .= '<tr>
									<td style="' . ($blocked == 1 ? 'border-left:3px solid #ec6513;' : '') . '">' . $i . '</td>                                    
                                    <td>' . $member_row['firstName'] . '</td>
                                    <td>' . $member_row['lastName'] . '</td>
                                    <td>' . ($member_row['sex'] == 'm' ? '<i class="fa fa-male"></i>' : '<i class="fa fa-female"></i>') . '</td>
									<td>' . $member_row['email'] . '</td>
									<td>' . $member_row['mobile'] . '</td>
									<td>' . date('Y-m-d', strtotime($member_row['registerDate'])) . '</td>
									<td style="text-align:center;">
										<button class="btn btn-primary btn-xs" onclick="iniEditMember(' . $member_row['id'] . ')"><i class="fa fa-pencil-square-o"></i> Edit</button>
										<button class="btn btn-primary btn-xs" style="' . ($blocked == 1 ? 'background:#ec6513;' : '') . '" onclick="iniComfirmMemberBlock(' . $member_row['id'] . ',' . $blocked . ')">' . ($blocked == 0 ? '<i class="fa fa-lock"></i> Block' : '<i class="fa fa-unlock-alt"></i> Unblock') . '</button>
									</td>
                               </tr>';
        $i++;
    }
    if ($memberListString == '') {
        $memberListString = '<tr><td colspan="8" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> No Member Found</td></tr>';
    }
    $data = array('list' => $memberListString, 'targetPage' => $targetPage, 'totalPages' => $totalPages, 'gotoSelectNum' => $gotoSelectNum, 'nav_btn_disable' => $nav_btn_disable);

    echo json_encode($data);
} elseif ($cmd == 'newMember_inup') {
    $getData = $_POST['getData'];
	$commissionrate = $_POST['commissionrate'];
    extract($getData);
    $datetime = date("Y-m-d H:i:s");

    if ($newMemberSubscribeEmail == 'true') {
        $newMemberSubscribeEmail = 1;
    } else {
        $newMemberSubscribeEmail = 0;
    }
    if ($newMemberSubscribeSMS == 'true') {
        $newMemberSubscribeSMS = 1;
    } else {
        $newMemberSubscribeSMS = 0;
    }
    $newMemberCompanyIntro = addslashes($newMemberCompanyIntro);

    $encryptKey = singleCell_qry("settingValue", "tblgeneralsetting", "settingName='encryption_key' LIMIT 1");
    $encryptPassword = encodeString($newMemberLoginPass, $encryptKey);

    $msg = '';
    $returnData = '';
    if (trim($newMemberTypeid) > 0 and trim($newMemberFirstName) <> '' and trim($newMemberLastName) <> '' and trim($newMemberLastName) <> '' and trim($newMemberEmail) <> '' and trim($newMemberLoginPass) <> '') {
        if (trim($newMemberLoginPass) == trim($newMemberConfirmLoginpass)) {
            if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tbluser WHERE email='$newMemberEmail'")) == 0) {
                exec_query_utf8("INSERT INTO tbluser SET userTypeid=$newMemberTypeid,firstName='$newMemberFirstName',lastName='$newMemberLastName',sex='$newMemberSex',email='$newMemberEmail',loginPassword='$encryptPassword', mobile='$newMemberMobile',address='$newMemberCompanyAddress',companyName='$newMemberCompanyName',companyPhone='$newMemberCompanyPhone',website='$newMemberCompanyWebsite',subscribeEmail=$newMemberSubscribeEmail,subscribeSMS=$newMemberSubscribeSMS,introduction='$newMemberCompanyIntro',commissionrate = $commissionrate,registerDate='$datetime'");
                $msg = 'success';
                $returnData = 'New member has been added successfully.';
            } else {
                $msg = 'exist';
            }
        } else {
            $msg = 'mismatch';
            $returnData = 'Password not match.';
        }
    } else {
        $msg = 'invalid';
        $returnData = 'Data not complete. Please fill all required data!';
    }
    $data = array('msg' => $msg, 'data' => $returnData);
    echo json_encode($data);
} elseif ($cmd == 'iniEditMember') {
    $id = post('id');

    $personalData = array();
    $companyData = array();
    $member_qry = exec_query_utf8("SELECT * FROM tbluser WHERE id=$id LIMIT 1");
    while ($member_row = mysqli_fetch_assoc($member_qry)) {
        $userTypeid = $member_row['userTypeid'];
        $glampingCompany = 1;
        $checkIfCompany = mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcategory WHERE id=$userTypeid AND categoryType=(SELECT id FROM tblcategorytype WHERE typeCode='user' LIMIT 1) AND code='Glamping Company' LIMIT 1"));

        if ($checkIfCompany == 0) {
            $glampingCompany = 0; 
        } 

        $personalData = array('glampingCompany' => $glampingCompany, 'userTypeid' => $userTypeid, 'firstName' => $member_row['firstName'], 'lastName' => $member_row['lastName'], 'sex' => $member_row['sex'], 'email' => $member_row['email'], 'mobile' => $member_row['mobile'], 'subscribeEmail' => $member_row['subscribeEmail'], 'subscribeSMS' => $member_row['subscribeSMS']); 

        $companyData = array('companyName' => $member_row['companyName'], 'companyIntro' => $member_row['introduction'], 'companyPhone' => $member_row['companyPhone'], 'address' => $member_row['address'], 'website' => $member_row['website'],'commissionrate' => $member_row['commissionrate']);
    }
    $data = array('personal' => $personalData, 'company' => $companyData);
    echo json_encode($data);
} elseif ($cmd == 'blockMember_block') {
    $id = post('id');

    $member_qry = exec_query_utf8("UPDATE tbluser SET blocked = NOT blocked WHERE id=$id LIMIT 1");
} elseif ($cmd == 'updateMemberPersonal_inup') {
    $id = post('id');
    $editMemberPerosnalData = $_POST['editMemberPerosnalData'];
    extract($editMemberPerosnalData);
    $datetime = date("Y-m-d H:i:s");
    if ($editMemberSubscribeEmail == 'true') {
        $editMemberSubscribeEmail = 1;
    } else {
        $editMemberSubscribeEmail = 0;
    }
    if ($editMemberSubscribeSMS == 'true') {
        $editMemberSubscribeSMS = 1;
    } else {
        $editMemberSubscribeSMS = 0;
    }

    if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tbluser WHERE email='$editMemberEmail' AND id<>$id")) == 0) {
        if (trim($editMemberEmail) <> '' or trim($editMemberFirstName) <> '' or trim($editMemberLastName) <> '' or $userTypeid > 0) {
            exec_query_utf8("UPDATE tbluser SET userTypeid=$editMemberTypeid,firstName='$editMemberFirstName',lastName='$editMemberLastName',sex='$editMemberSex', email='$editMemberEmail',mobile='$editMemberMobile',subscribeEmail=$editMemberSubscribeEmail,subscribeSMS=$editMemberSubscribeSMS,commissionrate=$Editcommissionrate,registerDate='$datetime' WHERE id=$id LIMIT 1");
        } else {
            echo 'invalid';
        }
    } else {
        echo 'exist';
    }
} elseif ($cmd == 'updateMemberCompany_inup') {
    $id = post('id');
    $editMemberCompanyData = $_POST['editMemberCompanyData'];
    extract($editMemberCompanyData);
    $datetime = date("Y-m-d H:i:s"); 
 
    if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tbluser WHERE companyName='$editCompanyName' AND id<>$id")) == 0) {












        if (trim($editCompanyName) <> '') {
            exec_query_utf8("UPDATE tbluser SET companyName='$editCompanyName',introduction='" . addslashes($editCompanyIntro) . "',companyPhone='$editCompanyPhone',address='$editCompanyAddress', website='$editCompanyWebsite',commissionrate=$Editcommissionrate,registerDate='$datetime' WHERE id=$id LIMIT 1");
        } else {
            echo 'invalid'; 
        } 
    } else { 
        echo 'exist'; 
    }  
} elseif ($cmd == 'portaladminList') { 
    $keyword = post("keyword");
    $levelid = post('levelid');
    $currentPage = intval(post("currentPage"));
    $rowsPerPage = post("rowsPerPage");
    $navAction = post("navAction");

    $sql_condition = 'WHERE';
    if ($levelid > 0) {
        $sql_condition.=" levelid=$levelid";
    }
    if ($keyword <> '') {
        $sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " (fullname LIKE '%$keyword%' OR email LIKE '%$keyword%')";
    }
    if ($sql_condition == 'WHERE') {
        $sql_condition = '';
    }
    //work with total page
    $navRow_qry = exec_query_utf8("SELECT * FROM tblportaladmin $sql_condition ORDER BY id DESC");
    $totalRow = mysqli_num_rows($navRow_qry);
    $totalPages = $totalRow / $rowsPerPage;
    if ($totalRow % $rowsPerPage > 0) {
        $totalPages = intval($totalPages) + 1;
    }

    //get the target page number	
    $targetPage = 1;
    $nav_btn_disable = array();
    if ($navAction == 'first') {
        $targetPage = 1;
    } elseif ($navAction == 'prev') {
        $targetPage = $currentPage - 1;
    } elseif ($navAction == 'next') {
        $targetPage = $currentPage + 1;
    } elseif ($navAction == 'last') {
        $targetPage = $totalPages;
    } elseif ($navAction == 'goto') {
        $targetPage = $currentPage;
    }
    //get goto select list
    $gotoSelectNum = array();
    for ($i = 1; $i <= $totalPages; $i++) {
        $gotoSelectNum[] = $i;
    }

    if ($totalPages == 1 or $totalPages == 0) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 0, 'nav_last' => 0);
    } elseif ($targetPage == 1) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 1, 'nav_last' => 1);
    } elseif ($targetPage == $totalPages) {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 0, 'nav_last' => 0);
    } else {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 1, 'nav_last' => 1);
    }

    $startIndex = ($targetPage - 1) * $rowsPerPage;
    $portalAdminListString = '';
    $i = $startIndex + 1;
    $portalAdmin_qry = exec_query_utf8("SELECT * FROM tblportaladmin $sql_condition ORDER BY id DESC LIMIT " . $startIndex . ",$rowsPerPage");
    while ($portalAdmin_row = mysqli_fetch_assoc($portalAdmin_qry)) {
        $blocked = $portalAdmin_row['blocked'];
        $levelName = '';
        $createdBy = '';
        $cate_qry = exec_query_utf8("SELECT * FROM tblcategory WHERE id=" . $portalAdmin_row['levelid'] . " LIMIT 1");
        while ($cate_row = mysqli_fetch_assoc($cate_qry)) {
            $levelName = $cate_row['categoryTitle'];
        }
        $createdBy_qry = exec_query_utf8("SELECT fullname FROM tblportaladmin WHERE id=" . $portalAdmin_row['createdBy'] . " LIMIT 1");
        while ($createdBy_row = mysqli_fetch_assoc($createdBy_qry)) {
            $createdBy = $createdBy_row['fullname'];
        }

        $portalAdminListString .= '<tr>
									<td style="' . ($blocked == 1 ? 'border-left:3px solid #ec6513;' : '') . '">' . $i . '</td>                                    
                                    <td>' . $portalAdmin_row['fullname'] . '</td>
                                    <td>' . $levelName . '</td>
                                    <td class="tableCellCenter">' . ($portalAdmin_row['sex'] == 'm' ? '<i class="fa fa-male"></i>' : '<i class="fa fa-female"></i>') . '</td>
									<td>' . $portalAdmin_row['mobile'] . '</td>
									<td>' . $portalAdmin_row['email'] . '</td>	
									<td>' . ($createdBy == '' ? 'None' : ucfirst($createdBy)) . '</td>								
									<td>' . date('Y-m-d', strtotime($portalAdmin_row['datetime'])) . '</td>
									<td style="text-align:center;">
										<button class="btn btn-primary btn-xs" onclick="loadPortalAdminData(' . $portalAdmin_row['id'] . ')"><i class="fa fa-pencil-square-o"></i> Edit</button>
										<button class="btn btn-primary btn-xs" style="' . ($blocked == 1 ? 'background:#ec6513;' : '') . '" onclick="comfirmBlockAdmin(' . $portalAdmin_row['id'] . ',' . $blocked . ')">' . ($blocked == 0 ? '<i class="fa fa-lock"></i> Block' : '<i class="fa fa-unlock-alt"></i> Unblock') . '</button>
									</td>
                               </tr>';
        $i++;
    }
    if ($portalAdminListString == '') {
        $portalAdminListString = '<tr><td colspan="9" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> No Admin Account Found</td></tr>';
    }
    $data = array('list' => $portalAdminListString, 'targetPage' => $targetPage, 'totalPages' => $totalPages, 'gotoSelectNum' => $gotoSelectNum, 'nav_btn_disable' => $nav_btn_disable);

    echo json_encode($data);
} elseif ($cmd == 'loadPortalAdminData') {
    $id = post('id');

    $data = array();
    $admin_qry = exec_query_utf8("SELECT * FROM tblportaladmin WHERE id=$id LIMIT 1");
    while ($admin_row = mysqli_fetch_assoc($admin_qry)) {
        $data = array('name' => $admin_row['fullname'], 'sex' => $admin_row['sex'], 'mobile' => $admin_row['mobile'], 'email' => $admin_row['email'], 'levelid' => $admin_row['levelid']);
    }
    echo json_encode($data);
} elseif ($cmd == 'loadMyProfile') {
    $id = $sessonid;
    $data = array();

    $admin_qry = exec_query_utf8("SELECT * FROM tblportaladmin WHERE id=$id LIMIT 1");
    while ($admin_row = mysqli_fetch_assoc($admin_qry)) {
        $data = array('name' => $admin_row['fullname'], 'sex' => $admin_row['sex'], 'mobile' => $admin_row['mobile'], 'email' => $admin_row['email'], 'levelid' => $admin_row['levelid']);
    }
    echo json_encode($data);
} elseif ($cmd == 'blockAdmin_block') {
    $id = post('id');

    exec_query_utf8("UPDATE tblportaladmin SET blocked = NOT blocked WHERE id=$id LIMIT 1");
} elseif ($cmd == 'newPortalAdmin_inup') {
    $newPortaladminData = $_POST['newPortaladminData'];
    extract($newPortaladminData);
    $datetime = date("Y-m-d H:i:s");
    $msg = '';
    $returnData = '';

    if ($editPortalAdminid > 0 and mysqli_num_rows(exec_query_utf8("SELECT id FROM tblportaladmin WHERE email='$newEmail' AND id<>$editPortalAdminid")) == 0) {
        if (trim($newFullname) <> '' and trim($newSex) <> '' and trim($newMobile) <> '' and trim($newEmail) <> '' and $newLevelid > 0) {
            if (trim($currentPassword) <> '') {
                if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblportaladmin WHERE email='$newEmail' AND loginPassword='$currentPassword'")) == 1) {
                    if (trim($newPassword) <> '' and trim($newPassword) == trim($newConfirmPassword)) {
                        //update all info and password
                        exec_query_utf8("UPDATE tblportaladmin SET fullname='$newFullname',levelid=$newLevelid,sex='$newSex',mobile='$newMobile',loginPassword='$newPassword',createdBy='" . $sessonid . "',datetime='$datetime' WHERE id=$editPortalAdminid LIMIT 1");
                        $msg = 'success';
                        $returnData = 'Admin account info and password updated.';
                    } else {
                        $msg = 'mismatch';
                        $returnData = 'New password not matched.';
                    }
                } else {
                    $msg = 'mismatch';
                    $returnData = 'Wrong current password.';
                }
            } else {
                //all info but not update password
                exec_query_utf8("UPDATE tblportaladmin SET fullname='$newFullname',levelid=$newLevelid,sex='$newSex',mobile='$newMobile',createdBy='" . $sessonid . "',datetime='$datetime' WHERE id=$editPortalAdminid LIMIT 1");
                $msg = 'success';
                $returnData = 'Admin account info updated.';
            }
        } else {
            $msg = 'invalid';
            $returnData = 'Invalid data. Admin account not updated.';
        }
    } elseif (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblportaladmin WHERE email='$newEmail'")) == 0) {
        if (trim($newFullname) <> '' and trim($newSex) <> '' and trim($newMobile) <> '' and trim($newEmail) <> '' and trim($newPassword) <> '' and $newLevelid > 0) {
            if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                if (trim($newPassword) == trim($newConfirmPassword)) {
                    exec_query_utf8("INSERT INTO tblportaladmin SET fullname='$newFullname',levelid=$newLevelid,sex='$newSex',mobile='$newMobile',email='$newEmail',loginPassword='$newPassword',createdBy='" . $sessonid . "',datetime='$datetime'");
                    $msg = 'success';
                    $returnData = 'New admin account inserted.';
                } else {
                    $msg = 'mismatch';
                    $returnData = 'Password not matched.';
                }
            } else {
                $msg = 'invalid';
                $returnData = 'Email is invalid.';
            }
        } else {
            $msg = 'invalid';
            $returnData = 'Invalid data. New admin account not added.';
        }
    } else {
        $msg = 'exist';
    }

    $data = array('msg' => $msg, 'data' => $returnData);
    echo json_encode($data);
} elseif ($cmd == 'editMyProfile') {
    $id = $sessonid;
    $editMyProfileData = $_POST['editMyProfileData'];
    extract($editMyProfileData);
    $datetime = date("Y-m-d H:i:s");
    $msg = '';
    $returnData = '';

    if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblportaladmin WHERE email='$email' AND id<>$id")) == 0) {
        if (trim($editFullname) <> '' and trim($editSex) <> '' and trim($editMobile) <> '') {
            if (trim($currentPassword) <> '') {
                if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblportaladmin WHERE email='$email' AND loginPassword='$currentPassword'")) == 1) {
                    if (trim($newPassword) <> '' and trim($newPassword) == trim($newConfirmPassword)) {
                        //update all info and password
                        exec_query_utf8("UPDATE tblportaladmin SET fullname='$editFullname',sex='$editSex',mobile='$editMobile',loginPassword='$newPassword',createdBy=$id,datetime='$datetime' WHERE id=$id LIMIT 1");
                        $msg = 'success';
                        $returnData = 'Profile info and password updated.';
                    } else {
                        $msg = 'mismatch';
                        $returnData = 'New password not matched.';
                    }
                } else {
                    $msg = 'mismatch';
                    $returnData = 'Wrong current password.';
                }
            } else {
                //all info but not update password
                exec_query_utf8("UPDATE tblportaladmin SET fullname='$editFullname',sex='$editSex',mobile='$editMobile',createdBy=$id,datetime='$datetime' WHERE id=$id LIMIT 1");
                $msg = 'success';
                $returnData = 'Profile info updated.';
            }
        } else {
            $msg = 'invalid';
            $returnData = 'Invalid data. Profile not updated.';
        }
    } else {
        $msg = 'exist';
        $returnData = 'Email exists! Profile not updated.';
    }

    $data = array('msg' => $msg, 'data' => $returnData);
    echo json_encode($data);
} elseif ($cmd == 'authenticaitonList') {
    $categoryListString = '';
    $i = 1;
    $category_qry = exec_query_utf8("SELECT * FROM tblcategory WHERE categoryType=(SELECT id FROM tblcategorytype WHERE typeCode='Portal Admin' AND active=1 LIMIT 1) AND active=1 ORDER BY id ASC");
    while ($category_row = mysqli_fetch_assoc($category_qry)) {

        $categoryListString .= '<tr>
									<td>' . $i . '</td>                                    
                                    <td>' . $category_row['categoryTitle'] . '</td>
                                    <td>' . $category_row['description'] . '</td>
                                    <td>' . $category_row['icon'] . '</td>
									<td>' . $category_row['datetime'] . '</td>
									<td style="text-align:center;">
										<button class="btn btn-primary btn-xs" onclick="loadAuthenticationSetting(' . $category_row['id'] . ',\'' . ucfirst($category_row['categoryTitle']) . '\')"><i class="fa fa-bolt"></i> Permission</button>
									</td>
                               </tr>';
        $i++;
    }
    if ($categoryListString == '') {
        $categoryListString = '<tr><td colspan="6" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> No Category Found</td></tr>';
    }

    echo $categoryListString;
} elseif ($cmd == 'loadAuthenticationSetting') {
    $id = post('id');

    $data = array();
    $totalTrue = 0;
    $totalAuthenticationCriteria = mysqli_num_rows(exec_query_utf8("SELECT id FROM tblauthenticationcriteria WHERE active=1 ORDER BY id ASC"));
    $category_qry = exec_query_utf8("SELECT * FROM tblcategory WHERE id=$id AND active=1 LIMIT 1");
    while ($category_row = mysqli_fetch_assoc($category_qry)) {
        $rawData = explode(',', $category_row['data']);
        if (count($rawData) > 0) {
            foreach ($rawData as $value) {
                $getData = explode(':', $value);
                if (count($getData) == 2) {
                    $data[] = array('authenid' => $getData[0], 'value' => $getData[1]);
                    if ($getData[1] == 1) {
                        $totalTrue++;
                    }
                }
            }
        }
    }

    if ($totalTrue >= $totalAuthenticationCriteria and count($rawData) == $totalAuthenticationCriteria) {
        echo 'all';
    } elseif ($totalTrue == 0) {
        echo 'none';
    } else {
        echo json_encode($data);
    }
} elseif ($cmd == 'updateAuthenticationSetting_inup') {
    $id = post('id');
    if ($id > 0) {
        $authenSettings = $_POST['authenSettings'];
        $settingData = '';
        foreach ($authenSettings as $key => $value) {
            $settingData .= ($settingData == '' ? '' : ',') . $key . ':' . $value;
        }
        exec_query_utf8("UPDATE tblcategory SET data='$settingData' WHERE id=$id LIMIT 1");
    } else {
        echo 'invalid';
    }
} elseif ($cmd == 'campingList') {
    $keyword = post("keyword");
    if (isset($_SESSION['adminid'])) {
        $companyid = post("companyid");
    } else {
        $companyid = $company_sessionid;
    }
    $campingType = post('campingType');
    $activeStatus = post('activeStatus');
    $pendingStatus = post('pendingStatus');
    $countryid = post('countryid');
    $cityid = post('cityid');
    $areaid = post('areaid');
    $currentPage = intval(post("currentPage"));
    $rowsPerPage = post("rowsPerPage");
    $navAction = post("navAction");

    //if($companyid==0 or $companyid==''){$companyid=$company_sessionid;}

    $sql_condition = 'WHERE';
    $areaid_array = array();
    if ($cityid == 0) {
        $location_qry = exec_query_utf8("SELECT id FROM tblsublocation WHERE cityid IN (SELECT id FROM tblmainlocation WHERE countryid=$countryid AND active=1) AND active=1");
        while ($location_row = mysqli_fetch_assoc($location_qry)) {
            $areaid_array[] = $location_row['id'];
        }
    } else {
        if ($areaid == 0) {
            $location_qry = exec_query_utf8("SELECT id FROM tblsublocation WHERE cityid=$cityid AND active=1");
            while ($location_row = mysqli_fetch_assoc($location_qry)) {
                $areaid_array[] = $location_row['id'];
            }
        } else {
            $areaid_array[] = $areaid;
        }
    }

    if (count($areaid_array) > 0) {
        $areaid_str = implode(',', $areaid_array);

        $sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " locationid IN ($areaid_str)";
    } else {
        $sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " locationid=0";
    }

    if ($companyid > 0) {
        $sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " userid = '$companyid'";
    }
    if ($keyword <> '') {
        $sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " campingName LIKE '%$keyword%'";
    }
    if ($campingType <> 'all') {
        $sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " campingCategory=$campingType";
    }
    if ($pendingStatus <> 'all') {
        $sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " pending=$pendingStatus";
    }
    if ($activeStatus <> 'all') {
        $sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " active=$activeStatus";
    }
    if ($sql_condition == 'WHERE') {
        $sql_condition = '';
    }
    //work with total page
    $navRow_qry = exec_query_utf8("SELECT * FROM tblcamping $sql_condition ORDER BY postedDate DESC");
    $totalRow = mysqli_num_rows($navRow_qry);
    $totalPages = $totalRow / $rowsPerPage;
    if ($totalRow % $rowsPerPage > 0) {
        $totalPages = intval($totalPages) + 1;
    }

    //get the target page number	
    $targetPage = 1;
    $nav_btn_disable = array();
    if ($navAction == 'first') {
        $targetPage = 1;
    } elseif ($navAction == 'prev') {
        $targetPage = $currentPage - 1;
    } elseif ($navAction == 'next') {
        $targetPage = $currentPage + 1;
    } elseif ($navAction == 'last') {
        $targetPage = $totalPages;
    } elseif ($navAction == 'goto') {
        $targetPage = $currentPage;
    }
    //get goto select list
    $gotoSelectNum = array();
    for ($i = 1; $i <= $totalPages; $i++) {
        $gotoSelectNum[] = $i;
    }

    if ($totalPages == 1 or $totalPages == 0) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 0, 'nav_last' => 0);
    } elseif ($targetPage == 1) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 1, 'nav_last' => 1);
    } elseif ($targetPage == $totalPages) {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 0, 'nav_last' => 0);
    } else {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 1, 'nav_last' => 1);
    }

    $startIndex = ($targetPage - 1) * $rowsPerPage;
    $campingListString = '';
    $i = $startIndex + 1;
    $camping_qry = exec_query_utf8("SELECT * FROM tblcamping $sql_condition ORDER BY id DESC LIMIT " . $startIndex . ",$rowsPerPage");
    while ($camping_row = mysqli_fetch_assoc($camping_qry)) {
        $blocked = $camping_row['active'];
        $totalView = $camping_row['view'];
        $companyName = 'N/A';
        $countryName = '';
        $cityName = '';
        $areaName = '';
        $imgUrl = '';
        $campingType = '';
        $campingArea = '';

        $user_qry = exec_query_utf8("SELECT companyName FROM tbluser WHERE id=" . $camping_row['userid'] . " LIMIT 1");
        while ($user_row = mysqli_fetch_assoc($user_qry)) {
            $companyName = ucfirst($user_row['companyName']);
        }

        $location_qry = exec_query_utf8("SELECT subLocationName FROM tblsublocation WHERE id=" . $camping_row['locationid'] . " LIMIT 1");
        while ($location_row = mysqli_fetch_assoc($location_qry)) {
            $areaName = ucfirst($location_row['subLocationName']);
        }
        $location_qry = exec_query_utf8("SELECT cityName FROM tblmainlocation WHERE id=(SELECT cityid FROM tblsublocation WHERE id=" . $camping_row['locationid'] . " LIMIT 1) LIMIT 1");
        while ($location_row = mysqli_fetch_assoc($location_qry)) {
            $cityName = ucfirst($location_row['cityName']);
        }
        $location_qry = exec_query_utf8("SELECT shortName FROM tblcountry WHERE id=(SELECT countryid FROM tblmainlocation WHERE id=(SELECT cityid FROM tblsublocation WHERE id=" . $camping_row['locationid'] . " LIMIT 1) LIMIT 1) LIMIT 1");
        while ($location_row = mysqli_fetch_assoc($location_qry)) {
            $countryName = ucfirst($location_row['shortName']);
        }

        $thumbnail_url = '/admin/uploadedimages/files/thumbnail/';
        $noimage = '/admin/images/noimage.jpg';
        $img_qry = exec_query_utf8("SELECT filename FROM tblphotogallery WHERE cateid=(SELECT id FROM tblcategory WHERE code='Camping' LIMIT 1) AND rowid=" . $camping_row['id'] . " ORDER BY id ASC LIMIT 1");
        while ($img_row = mysqli_fetch_assoc($img_qry)) {
            $imgUrl = $thumbnail_url . $img_row['filename'];
        }

        $cate_qry = exec_query_utf8("SELECT * FROM tblcategory WHERE id IN (" . $camping_row['campingCategory'] . ") AND active=1");
        while ($cate_row = mysqli_fetch_assoc($cate_qry)) {
            $campingType .= ($campingType == '' ? '' : '&nbsp;&nbsp;|&nbsp;&nbsp;') . $cate_row['icon'] . '&nbsp;&nbsp;' . ucfirst($cate_row['categoryTitle']);
        }

        $cate_qry = exec_query_utf8("SELECT * FROM tblcategory WHERE id IN (" . ($camping_row['campingArea'] == '' ? "''" : $camping_row['campingArea']) . ") AND active=1");
        while ($cate_row = mysqli_fetch_assoc($cate_qry)) {
            $campingArea .= ($campingArea == '' ? '' : ' | ') . $cate_row['icon'] . ' ' . ucfirst($cate_row['categoryTitle']);
        }

        $totalRoom_qry = mysqli_fetch_assoc(exec_query_utf8("SELECT sum(total) as totalRoom FROM tblcampingroom WHERE campingid=" . $camping_row['id'] . " AND active=1"));
        $totalRoom = $totalRoom_qry['totalRoom'];
        $totalRoom = $totalRoom == '' ? 0 : $totalRoom;

        $active = $camping_row['active'];
        $pending = $camping_row['pending'];

        $campingListString .= '<tr>
                                            	<td class="tableCellCenter" style="' . ($blocked == 0 ? 'border-left:3px solid #ec6513;' : '') . '">' . $i . '</td>    
                                                <td>
                                                	 <table class="table table-striped table-bordered table-hover" style="margin:0;">
                                                     	<tbody>
                                                        	<tr height="120">
                                                            	<td rowspan="2" width="150" style="background:url(' . ($imgUrl == '' ? $noimage : $imgUrl) . ') center center no-repeat; background-size:cover;"></td>
                                                                <td class="campingInfo">
                                                                	<div class="campingInfo_title">' . ucfirst($camping_row['campingName']) . ' ' . ($campingArea == '' ? '' : '(' . $campingArea . ')') . '
																		<span style="color:#c41006;">' . ($active == 1 ? ($pending == 0 ? '' : '(Pending)') : '(Inactive)') . '</span></div>
                                                                    ' . (isset($_SESSION['adminid']) ? '<div><i class="fa fa-building-o"></i> ' . ($companyName == '' ? 'None' : $companyName) . '</div>' : '') . '
																	<div>' . $campingType . '</div>
																	<div>
																		<div><i class="fa fa-home"></i> ' . $totalRoom . ' rooms</div>
																		<div><i class="fa fa-eye"></i> ' . ($totalView == '' ? '0' : $totalView) . ' view' . ($totalView > 1 ? 's' : '') . '</div>
																		<div><i class="fa fa-clock-o"></i> ' . date('d/m/Y', strtotime($camping_row['postedDate'])) . '</div>																	
																	</div>
                                                                </td>
                                                            </tr>
                                                            <tr height="30">
                                                                <td>
                                                                    <div style="font-size:11px; font-weight:bold; color:#c1c1c1;">
                                                                    	<a href="#"><i class="fa fa-map-marker"></i> ' . $countryName . '</a> <i class="fa fa-angle-right"></i> 
                                                                        <a href="#">' . $cityName . '</a> <i class="fa fa-angle-right"></i> 
                                                                        <a href="#">' . $areaName . '</a>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                     </table>
                                                </td>
												
                                                <td class="tableCellCenter action_td_verticle">
													 <div><a href="/reservation/' . encodeString($camping_row['id'], "camping") . '" target="_new"><button type="submit" class="btn btn-primary btn-xs" ><i class="fa fa-calendar fa-fw"></i> ê°€ìš©ì„±</button></a></div>
                                                	 <div><a href="/camp/' . encodeString($camping_row['id'], "camping") . '" target="_new"><button type="submit" class="btn btn-primary btn-xs" ><i class="fa fa-eye fa-fw"></i>View</button></a></div>
                                                     <div><a href="/admin/editcamping?id=' . $camping_row['id'] . '"><button type="submit" class="btn btn-primary btn-xs" ><i class="fa fa-pencil-square-o fa-fw"></i> íŽ¸ì§‘</button></a></div>
                                                     <div><button type="submit" class="btn btn-primary btn-xs" style="' . ($active == 0 ? 'background:#ec6513;' : '') . '" onclick="comfirmCampDeactivate(' . $camping_row['id'] . ',' . $active . ')" >' . ($active == 1 ? '<i class="fa fa-lock"></i> Deactive ' : '<i class="fa fa-unlock-alt"></i>  Active ') . '</button></div>
                                                     ' . ($pending == 1 ? '<div><button type="submit" class="btn btn-primary btn-xs" onclick="comfirmCampApproval(' . $camping_row['id'] . ',' . $pending . ')" ><i class="fa fa-check-square-o fa-fw"></i> Approve</button></div>' : '') . '
                                                </td>
                                            </tr>';
        $i++;
    }
    if ($campingListString == '') {
        $campingListString = '<tr><td colspan="3" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> No Camping Found</td></tr>';
    }
    $data = array('list' => $campingListString, 'targetPage' => $targetPage, 'totalRow' => $totalRow, 'totalPages' => $totalPages, 'gotoSelectNum' => $gotoSelectNum, 'nav_btn_disable' => $nav_btn_disable);

    echo json_encode($data);
} elseif ($cmd == 'deactivateCamp_block') {
    $id = post('id');

    $camp_qry = exec_query_utf8("UPDATE tblcamping SET active = NOT active WHERE id=$id LIMIT 1");
    //$camp_qry = exec_query_utf8("delete from tblcampingroom WHERE campingid = $id");
    //$camp_qry = exec_query_utf8("delete from tblcamping WHERE id=$id");
} elseif ($cmd == 'approveCamp_appr') {
    $id = post('id');

    $camp_qry = exec_query_utf8("UPDATE tblcamping SET pending = NOT pending WHERE id=$id LIMIT 1");
} elseif ($cmd == 'areaSelectList') {
    $id = post('id');
    //$selectList='';
    $selectList = '<option value="0">--- Select ---</option>';
    $location_qry = exec_query_utf8("SELECT * FROM tblsublocation WHERE cityid=$id AND active=1 ORDER BY subLocationName ASC");
    while ($location_row = mysqli_fetch_assoc($location_qry)) {
        $selectList .= '<option value="' . $location_row['id'] . '">' . $location_row['subLocationName'] . '</option>';
    }

    $mapCoordinate = array();
    $location_qry = exec_query_utf8("SELECT mapCoordinate FROM tblmainlocation WHERE id=$id AND active=1 LIMIT 1");
    while ($location_row = mysqli_fetch_assoc($location_qry)) {
        $mapCoordinate = explode(',', $location_row['mapCoordinate']);
    }
    if ($id == 0) {
        $data = array('list' => $selectList, 'lat' => 0, 'lng' => 0, 'zoom' => 0);
    } else {
        $data = array('list' => $selectList, 'lat' => $mapCoordinate[0], 'lng' => $mapCoordinate[1], 'zoom' => str_replace('z', '', $mapCoordinate[2]));
    }
    echo json_encode($data);
} elseif ($cmd == 'focusAreaMap') {
    $id = post('id');
    $mapCoordinate = array();
    $location_qry = exec_query_utf8("SELECT mapCoordinate FROM tblsublocation WHERE id=$id AND active=1 LIMIT 1");
    while ($location_row = mysqli_fetch_assoc($location_qry)) {
        $mapCoordinate = explode(',', $location_row['mapCoordinate']);
    }

    $data = array('lat' => $mapCoordinate[0], 'lng' => $mapCoordinate[1], 'zoom' => str_replace('z', '', $mapCoordinate[2]));
    echo json_encode($data);
} elseif ($cmd == 'focusCampMap') {
    $id = post('id');
    $mapCoordinate = array();
    $camp_qry = exec_query_utf8("SELECT mapCoordinate FROM tblcamping WHERE id=$id LIMIT 1");
    while ($camp_row = mysqli_fetch_assoc($camp_qry)) {
        $mapCoordinate = explode(',', $camp_row['mapCoordinate']);
    }

    $data = array('lat' => $mapCoordinate[0], 'lng' => $mapCoordinate[1], 'zoom' => str_replace('z', '', $mapCoordinate[2]));
    echo json_encode($data);
} elseif ($cmd == 'newCamping_inup') {
    $newCampingData = $_POST['newCampingData'];
    extract($newCampingData);
    $datetime = date("Y-m-d H:i:s");

    $msg = '';
    $returnData = '';
    if ($campingid > 0) {
        if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcamping WHERE userid=$companyid AND campingName='$campingName' and id<>$campingid")) == 0) {
            if (trim($campingName) <> '' and $companyid > 0 and $campTypeid > 0 and $areaid > 0) {
                exec_query_utf8("UPDATE tblcamping SET userid=$companyid,campingName='$campingName',campingCategory='$campTypeid',campingArea='$campAreaid',amenity='$selectedAmenity',description='" . addslashes($campDes) . "',locationid=$areaid,address='$campAddress',mapCoordinate='$mapCoordinate',allowedPeriod=$campAllowedPeriod,checkin='$checkin',checkout='$checkout',generalPolicy='" . addslashes($generalPolicy) . "',refundPolicy='" . addslashes($refundPolicy) . "',minPeriod=$campMinPeriod,pending=$postingOption,postedDate='$datetime',postingAdminid=" . $sessonid . ", hasOwnReservation=$hasOwnReservation, ownReservationDes='$hasOwnReservationDes' WHERE id=$campingid LIMIT 1");
                $msg = 'updated';
            } else {
                $msg = 'invalid';
            }
        } else {
            $msg = 'exist';
        }
    } else {
//        / start insert by admin and company /
                if(isset($_SESSION['adminid'])){
                    if(mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcamping WHERE userid=$companyid AND campingName='$campingName'"))==0){
                            if(trim($campingName)<>'' and $companyid>0 and $campTypeid>0 and $areaid>0){
                                    exec_query_utf8("INSERT INTO tblcamping SET userid=$companyid,campingName='$campingName',campingCategory='$campTypeid',campingArea='$campAreaid',amenity='$selectedAmenity',description='".addslashes($campDes)."',locationid=$areaid,address='$campAddress',mapCoordinate='$mapCoordinate',allowedPeriod=$campAllowedPeriod,checkin='$checkin',checkout='$checkout',generalPolicy='".addslashes($generalPolicy)."',refundPolicy='".addslashes($refundPolicy)."',minPeriod=$campMinPeriod,pending=$postingOption,postedDate='$datetime',postingAdminid= $sessonid, hasOwnReservation=$hasOwnReservation, ownReservationDes='$hasOwnReservationDes' ");
                                    $msg = 'inserted';
                                    $returnData=mysqli_insert_id($conn);
                            }else{
                                    $msg = 'invalid';
                            }
                    }else{
                            $msg = 'exist';
                    }
                }else{
                    $userid=decodeString($_SESSION['userid'],$encryptKey) ;
                    if(mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcamping WHERE userid=$userid AND campingName='$campingName'"))==0){
                            if(trim($campingName)<>'' and $campTypeid>0 and $areaid>0){
                                    exec_query_utf8("INSERT INTO tblcamping SET userid=$userid,campingName='$campingName',campingCategory='$campTypeid',campingArea='$campAreaid',amenity='$selectedAmenity',description='".addslashes($campDes)."',locationid=$areaid,address='$campAddress',mapCoordinate='$mapCoordinate',allowedPeriod=$campAllowedPeriod,checkin='$checkin',checkout='$checkout',generalPolicy='".addslashes($generalPolicy)."',refundPolicy='".addslashes($refundPolicy)."',minPeriod=$campMinPeriod,pending=$postingOption,postedDate='$datetime',postingAdminid= $sessonid, hasOwnReservation=$hasOwnReservation, ownReservationDes='$hasOwnReservationDes' ");
                                    $msg = 'inserted';
                                    $returnData=mysqli_insert_id($conn);
                            }else{
                                    $msg = 'invalid';
                            }
                    }else{
                            $msg = 'exist';
                    }
                }
//                / end insert by admin and company /
//        if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcamping WHERE userid=$companyid AND campingName='$campingName'")) == 0) {
//            if (trim($campingName) <> '' and $companyid > 0 and $campTypeid > 0 and $areaid > 0) {
//                exec_query_utf8("INSERT INTO tblcamping SET userid=$companyid,campingName='$campingName',campingCategory='$campTypeid',campingArea='$campAreaid',amenity='$selectedAmenity',description='" . addslashes($campDes) . "',locationid=$areaid,address='$campAddress',mapCoordinate='$mapCoordinate',allowedPeriod=$campAllowedPeriod,checkin='$checkin',checkout='$checkout',generalPolicy='" . addslashes($generalPolicy) . "',refundPolicy='" . addslashes($refundPolicy) . "',minPeriod=$campMinPeriod,pending=$postingOption,postedDate='$datetime',postingAdminid=" . $sessonid);
//                $msg = 'inserted';
//                $returnData = mysqli_insert_id($conn);
//            } else {
//                $msg = 'invalid';
//            }
//        } else {
//            $msg = 'exist';
//        }
    }
    $data = array('msg' => $msg, 'data' => $returnData);
    echo json_encode($data);
} elseif ($cmd == 'roomList') {
    $campingid = post('campingid');
    $selectedid = post('selectedid');

    $room_qry = exec_query_utf8("SELECT * FROM tblcampingroom WHERE campingid=$campingid AND active=1");
    // $room_qry = exec_query_utf8("SELECT * FROM tblcampingroom WHERE campingid=464 AND active=1");
    $roomList = '';
    $roomSelectLIst = '<option value="0">--- ?? ---</option>';

    $i = 1;
    while ($room_row = mysqli_fetch_assoc($room_qry)) {
        $roomList .= '<tr><td>' . $i . '</td><td>' . $room_row['roomName'] . '</td><td class="tableCellCenter">' . $room_row['total'] . '</td><td class="tableCellCenter">
		<span class="myActiveBtn" onClick="loadRoomData(' . $room_row['id'] . ');"><i class="fa fa-pencil-square-o"></i></span>
		<span class="myActiveBtn" onClick="comfirmDeleteRoom(' . $room_row['id'] . ',\'' . $room_row['roomName'] . '\');"><i class="fa fa-times-circle-o"></i></span>
		<span class="myActiveBtn" id="highlight_btn_' . $room_row['id'] . '" onClick="highlightRoom(' . $room_row['id'] . ');"><i class="fa fa-eye"></i></span>
		</td></tr>';

        $roomSelectLIst .= '<option value="' . $room_row['id'] . '" ' . ($room_row['id'] == $selectedid ? 'selected' : '') . '>' . $room_row['roomName'] . '</option>';
        $i++;
    }
    //echo $roomList; // Add new one 
    if ($roomList == '') {
        $roomList = '<tr><td colspan="4" class="tableCellCenter">No Room</td></tr>';
    }
    $data = array('list' => $roomList, 'selectList' => $roomSelectLIst);

    echo json_encode($data);

} elseif ($cmd == 'campingSelectList') {
    $userid = post('userid');
    $selectedid = post('selectedid');

    $campingListString = '<option value="0">--- select ---</option>';
    $camp_qry = exec_query_utf8("SELECT * FROM tblcamping WHERE userid=$userid AND active=1 ORDER BY campingName ASC");
    while ($camp_row = mysqli_fetch_assoc($camp_qry)) {
        $campingListString .= '<option value="' . $camp_row['id'] . '" ' . ($camp_row['id'] == $selectedid ? 'selected' : '') . '>' . $camp_row['campingName'] . '</option>';
    }

    echo $campingListString;
} elseif ($cmd == 'addNewRoom_inup') {
    $newRoomData = $_POST['newRoomData'];
    extract($newRoomData);
    $datetime = date("Y-m-d H:i:s");

    $msg = '';
    $returnData = '';
    if ($editRoomid > 0 and mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcampingroom WHERE campingid=$campingid AND roomName='$newRoomName' AND id<>$editRoomid")) == 0) {
        exec_query_utf8("UPDATE tblcampingroom SET roomName='$newRoomName',total=$newRoomQuantity,minPeople=$newRoomMinPeople,maxPeople=$newRoomMaxPeople,unitExtraPrice=$newRoomExtraPrice,maxChildren=$newRoomMaxChildren,unitExtraChildrenPrice=$newRoomExtraChildrenPrice,description='" . addslashes($newRoomDes) . "',datetime='$datetime',enableextrapeople=$enableExtraPerson,active=1 WHERE id=$editRoomid LIMIT 1");
        $msg = 'updated';

        //adding each room to tblcampingeachroom
        $room_qry = exec_query_utf8("SELECT * FROM tblcampingeachroom WHERE roomid=$editRoomid ORDER BY roomNumber ASC");
        while ($room_row = mysqli_fetch_assoc($room_qry)) {
            $eachRoomid = $room_row['id'];
            if ($room_row['roomNumber'] > $newRoomQuantity) {
                if ($room_row['active'] == 1) {
                    exec_query_utf8("UPDATE tblcampingeachroom SET datetime='$datetime',active=0 WHERE id=$eachRoomid LIMIT 1");
                }
            } else {
                if ($room_row['active'] == 0) {
                    exec_query_utf8("UPDATE tblcampingeachroom SET datetime='$datetime',active=1 WHERE id=$eachRoomid LIMIT 1");
                }
            }
        }
        $curTotalRoom = mysqli_num_rows($room_qry);
        if ($curTotalRoom < $newRoomQuantity) {
            for ($i = $curTotalRoom + 1; $i <= $newRoomQuantity; $i++) {
                exec_query_utf8("INSERT INTO tblcampingeachroom SET roomid=$editRoomid,roomNumber=$i,location='0px,0px',datetime='$datetime'");
            }
        }
    } elseif (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcampingroom WHERE campingid=$campingid AND roomName='$newRoomName'")) == 0) {
        if (trim($newRoomName) <> '' and $campingid > 0 and $newRoomQuantity > 0) {
            exec_query_utf8("INSERT INTO tblcampingroom SET roomName='$newRoomName',campingid=$campingid,total=$newRoomQuantity,minPeople=$newRoomMinPeople,maxPeople=$newRoomMaxPeople,unitExtraPrice=$newRoomExtraPrice,maxChildren=$newRoomMaxChildren,unitExtraChildrenPrice=$newRoomExtraChildrenPrice,description='" . addslashes($newRoomDes) . "',datetime='$datetime',enableextrapeople=$enableExtraPerson");
            $msg = 'inserted';
            //adding each room to tblcampingeachroom
            $lastid = mysqli_insert_id($conn);
            for ($i = 1; $i <= $newRoomQuantity; $i++) {
                exec_query_utf8("INSERT INTO tblcampingeachroom SET roomid=$lastid,roomNumber=$i,location='0px,0px',datetime='$datetime'");
            }
        } else {
            $msg = 'invalid';
        }
    } else {
        if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcampingroom WHERE campingid=$campingid AND roomName='$newRoomName' AND active=0")) > 0) {
            exec_query_utf8("UPDATE tblcampingroom SET total=$newRoomQuantity,description='" . addslashes($newRoomDes) . "',datetime='$datetime',enableextrapeople=$enableExtraPerson,active=1 WHERE campingid=$campingid AND roomName='$newRoomName' LIMIT 1");
            $msg = 'updated';
            //adding each room to tblcampingeachroom
            $room_qry = exec_query_utf8("SELECT * FROM tblcampingeachroom WHERE roomid=$editRoomid ORDER BY roomNumber ASC");
            while ($room_row = mysqli_fetch_assoc($room_qry)) {
                $eachRoomid = $room_row['id'];
                if ($room_row['roomNumber'] > $newRoomQuantity) {
                    if ($room_row['active'] == 1) {
                        exec_query_utf8("UPDATE tblcampingeachroom SET datetime='$datetime',active=0 WHERE id=$eachRoomid LIMIT 1");
                    }
                } else {
                    if ($room_row['active'] == 0) {
                        exec_query_utf8("UPDATE tblcampingeachroom SET datetime='$datetime',active=1 WHERE id=$eachRoomid LIMIT 1");
                    }
                }
            }
            $curTotalRoom = mysqli_num_rows($room_qry);
            if ($curTotalRoom < $newRoomQuantity) {
                for ($i = $curTotalRoom + 1; $i <= $newRoomQuantity; $i++) {
                    exec_query_utf8("INSERT INTO tblcampingeachroom SET roomid=$editRoomid,roomNumber=$i,location='0px,0px',datetime='$datetime'");
                }
            }
        } else {
            $msg = 'exist';
        }
    }

    $data = array('msg' => $msg, 'data' => $returnData);
    echo json_encode($data);
} elseif ($cmd == 'deleteRoom_del') {
    $roomid = post('roomid');
    exec_query_utf8("UPDATE tblcampingroom SET active=0 WHERE id=$roomid LIMIT 1");
    echo '';
} elseif ($cmd == 'loadRoomData') {
    $id = post('id');

    $data = array();
    $room_qry = exec_query_utf8("SELECT * FROM tblcampingroom WHERE id=$id LIMIT 1");
    while ($room_row = mysqli_fetch_assoc($room_qry)) {
        $data = array('roomName' => $room_row['roomName'], 'roomQuantity' => $room_row['total'], 'minPeople' => $room_row['minPeople'], 'maxPeople' => $room_row['maxPeople'], 'unitExtraPrice' => $room_row['unitExtraPrice'], 'maxChildren' => $room_row['maxChildren'], 'unitExtraChildrenPrice' => $room_row['unitExtraChildrenPrice'], 'roomDes' => $room_row['description'],'enableextrapeople' =>$room_row['enableextrapeople']);
    }
    echo json_encode($data);
} elseif ($cmd == 'setRoomPrice_inup') {
    $priceFilterData = $_POST['priceFilterData'];
    $roomPrice = $_POST['roomPrice'];
    $datetime = date("Y-m-d H:i:s");
    extract($priceFilterData);

    $msg = 'invalid';
    $returnData = '';
    $weekDays = array('fri', 'sat', 'sun');
    if ($roomid > 0) {
        foreach ($roomPrice as $key => $value) {
            $id_parts = explode('_', $key); //split id/occasion type to parts
            $occasionType = $id_parts[0] . ',' . $id_parts[1]; //create std occasion type. ex: nseason,weekend
            $weekendPrice = '';
            if (count($id_parts) > 2) {// if weekend price, concatenate all prices of weekend days to one field
                foreach ($weekDays as $wValue) { //loop for all weekend days
                    $weekendPrice .= ($weekendPrice == '' ? '' : ',') . $roomPrice[$id_parts[0] . '_' . $id_parts[1] . '_' . $wValue];
                }
                $priceData = array('priceList', "'" . $weekendPrice . "'");
            } else {
                $priceData = array('price', $value);
            }

            //if($roomid>0){
            if ($priceOption == 1) {
                $totalRecord = mysqli_num_rows(exec_query_utf8("SELECT * FROM tblcampingroomprice WHERE roomid=$roomid AND occasionType='$occasionType'"));
                if ($totalRecord == 0) {
                    exec_query_utf8("INSERT INTO tblcampingroomprice(roomid,occasionType," . $priceData[0] . ",setDatetime) VALUES($roomid,'$occasionType'," . $priceData[1] . ",'$datetime')");
                    $msg = 'inserted';
                } else {
                    exec_query_utf8("UPDATE tblcampingroomprice SET " . $priceData[0] . "=" . $priceData[1] . ",setDatetime='$datetime' WHERE roomid=$roomid AND occasionType='$occasionType' LIMIT 1");
                    $msg = 'updated';
                }
            } elseif ($priceOption == 2) {
                if ($specificRoomid > 0) {
                    $totalRecord = mysqli_num_rows(exec_query_utf8("SELECT * FROM tblcampingeachroomprice WHERE eachRoomid=$specificRoomid AND occasionType='$occasionType'"));
                    if ($totalRecord == 0) {
                        exec_query_utf8("INSERT INTO tblcampingeachroomprice(eachRoomid,occasionType," . $priceData[0] . ",setDatetime) VALUES($specificRoomid,'$occasionType'," . $priceData[1] . ",'$datetime')");
                        $msg = 'inserted';
                    } else {
                        exec_query_utf8("UPDATE tblcampingeachroomprice SET " . $priceData[0] . "=" . $priceData[1] . ",setDatetime='$datetime' WHERE eachRoomid=$specificRoomid AND occasionType='$occasionType' LIMIT 1");
                        $msg = 'updated';
                    }
                }
            }
            //}
        }
    } else {
        $msg = 'invalid';
    }

    $data = array('msg' => $msg, 'data' => $returnData);
    echo json_encode($data);
} elseif ($cmd == 'loadRoomPrice') {
    $priceFilterData = $_POST['priceFilterData'];
    extract($priceFilterData);

    $data = array();
    $msg = 0;
    if ($roomid > 0) {
        if ($priceOption == 1) {
            $msg = 1;
            $roomPrice_qry = exec_query_utf8("SELECT occasionType,price,priceList FROM tblcampingroomprice WHERE roomid=$roomid AND active=1");
            while ($roomPrice_row = mysqli_fetch_assoc($roomPrice_qry)) {
                $occasionType = str_replace(',', '_', $roomPrice_row['occasionType']);
                if (stripos($occasionType, "weekend") !== false) {
                    $price = $roomPrice_row['priceList'];
                } else {
                    $price = number_format($roomPrice_row['price'], 0, ".", "");
                }
                $data[$occasionType] = $price;
            }
        } elseif ($priceOption == 2) {
            if ($priceStatus == 2) {
                $msg = 1;
                $roomPrice_qry = exec_query_utf8("SELECT occasionType,price,priceList FROM tblcampingeachroomprice WHERE eachRoomid=$specificRoomid AND active=1");
                while ($roomPrice_row = mysqli_fetch_assoc($roomPrice_qry)) {
                    $occasionType = str_replace(',', '_', $roomPrice_row['occasionType']);
                    if (stripos($occasionType, "weekend") !== false) {
                        $price = $roomPrice_row['priceList'];
                    } else {
                        $price = number_format($roomPrice_row['price'], 0, ".", "");
                    }
                    $data[$occasionType] = $price;
                }
            }
        }
    }

    echo json_encode(array('data' => $data, 'msg' => $msg));
} elseif ($cmd == 'roomByPriceType') {
    $priceStatus = post('priceStatus');
    $roomid = post('roomid');

    $roomList_gen = '';
    $roomList_cus = '';
    $eachRoom_qry = exec_query_utf8("SELECT * FROM tblcampingeachroom WHERE roomid=$roomid AND active=1");
    while ($eachRoom_row = mysqli_fetch_assoc($eachRoom_qry)) {
        $eachRoomid = $eachRoom_row['id'];
        $eachRoomName = ucfirst($eachRoom_row['useCustomName'] == 1 ? $eachRoom_row['customName'] : singleCell_qry("roomName", "tblcampingroom", "id=" . $eachRoom_row['roomid']) . ' ' . $eachRoom_row['roomNumber']);
        if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblcampingeachroomprice WHERE eachRoomid=$eachRoomid AND active=1")) > 0) {
            $roomList_cus .= '<option value="' . $eachRoomid . '">' . $eachRoomName . '</option> ';
        } else {
            $roomList_gen .= '<option value="' . $eachRoomid . '">' . $eachRoomName . '</option> ';
        }
    }

    if ($priceStatus == 1) {
        echo $roomList_gen;
    } elseif ($priceStatus == 2) {
        echo $roomList_cus;
    } 
} elseif ($cmd == 'menuList') {
    $campingid = post('campingid');

    $menu_qry = exec_query_utf8("SELECT * FROM tblitemtype WHERE campid=$campingid AND active=1");
    $menuList = '';
    $menuSelectLIst = '<option value="0">--- ì„ íƒ ---</option>'; 
    $i = 1;
    while ($menu_row = mysqli_fetch_assoc($menu_qry)) {
        //$quantity = explode(',',$menu_row['quantity']);
        $menuList .= '<tr><td>' . $i . '</td><td>' . $menu_row['name'] . '</td><td class="tableCellCenter">
		<span class="myActiveBtn" onClick="loadMenuData(' . $menu_row['id'] . ');"><i class="fa fa-pencil-square-o"></i></span>
		<span class="myActiveBtn" onClick="comfirmDeleteMenu(' . $menu_row['id'] . ',\'' . $menu_row['name'] . '\');"><i class="fa fa-times-circle-o"></i></span>
		</td></tr>';
        $menuSelectLIst .= '<option value="' . $menu_row['id'] . '">' . $menu_row['name'] . '</option>';
        $i++;
    }
    if ($menuList == '') {
        $menuList = '<tr><td colspan="6" class="tableCellCenter">ìž…ë ¥í•˜ì‹  ì •ë³´ê°€ ì—†ìŠµë‹ˆë‹¤</td></tr>';
    }
    $data = array('list' => $menuList, 'selectList' => $menuSelectLIst);
    echo json_encode($data);
} elseif ($cmd == 'deleteMenu_del') {
    $menuid = post('menuid');
    exec_query_utf8("UPDATE tblitemtype SET active=0 WHERE id=$menuid LIMIT 1");
    echo '';
} elseif ($cmd == 'loadMenuData') {
    $id = post('id');

    $data = array();
    $menu_qry = exec_query_utf8("SELECT * FROM tblitemtype WHERE id=$id LIMIT 1");
    while ($menu_row = mysqli_fetch_assoc($menu_qry)) {
        $quantity = explode(',', $menu_row['quantity']);
        $data = array('menuName' => $menu_row['name'], 'minQ' => $quantity[0], 'maxQ' => $quantity[1], 'interval' => $quantity[2], 'des' => $menu_row['des']);
    }
    echo json_encode($data);
} elseif ($cmd == 'addNewMenu_inup') {
    $newMenuData = $_POST['newMenuData'];
    extract($newMenuData);
    $datetime = date("Y-m-d H:i:s");
    //$quantity = $newMenuMinQuantity.','.$newMenuMaxQuantity.','.$newMenuInterval;

    $msg = '';
    $returnData = '';
    if ($editMenuid > 0 and mysqli_num_rows(exec_query_utf8("SELECT id FROM tblitemtype WHERE campid=$campingid AND name='$newMenuName' AND id<>$editMenuid")) == 0) {
        exec_query_utf8("UPDATE tblitemtype SET name='$newMenuName',des='" . addslashes($newMenuDes) . "',datetime='$datetime',active=1 WHERE id=$editMenuid LIMIT 1");
        $msg = 'updated';
    } elseif (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblitemtype WHERE campid=$campingid AND name='$newMenuName'")) == 0) {
        if (trim($newMenuName) <> '' and $campingid > 0 /* and $newMenuMaxQuantity>0 and $newMenuMinQuantity>0 and $newMenuInterval>0 */) {
            exec_query_utf8("INSERT INTO tblitemtype SET name='$newMenuName',campid=$campingid,des='" . addslashes($newMenuDes) . "',datetime='$datetime'");
            $msg = 'inserted';
        } else {
            $msg = 'invalid';
        }
    } else {
        if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblitemtype WHERE campid=$campingid AND name='$newMenuName' AND active=0")) > 0) {
            exec_query_utf8("UPDATE tblitemtype SET des='" . addslashes($newMenuDes) . "',datetime='$datetime',active=1 WHERE campid=$campingid AND name='$newMenuName' LIMIT 1");
            $msg = 'updated';
        } else {
            $msg = 'exist';
        }
    }

    $data = array('msg' => $msg, 'data' => $returnData);
    echo json_encode($data);
} elseif ($cmd == 'menuItemList') {
    $menuid = post('menuid');

    $item_qry = exec_query_utf8("SELECT * FROM tblitem WHERE type=$menuid AND active=1");
    $itemList = '';
    $i = 1;
    while ($item_row = mysqli_fetch_assoc($item_qry)) {
        $quantity = explode(',', $item_row['quantity']);
        $itemList .= '<tr><td>' . $i . '</td><td>' . $item_row['itemName'] . '</td><td>' . $quantity[0] . '</td><td>' . $quantity[1] . '</td><td>' . $quantity[2] . '</td><td>' . $item_row['price'] . '</td><td class="tableCellCenter">
		<span class="myActiveBtn" onClick="loadMenuItemData(' . $item_row['id'] . ');"><i class="fa fa-pencil-square-o"></i></span>
		<span class="myActiveBtn" onClick="comfirmDeleteMenuItem(' . $item_row['id'] . ',\'' . $item_row['itemName'] . '\');"><i class="fa fa-times-circle-o"></i></span>
		</td></tr>';
        $i++;
    }
    if ($itemList == '') {
        $itemList = '<tr><td colspan="7" class="tableCellCenter">ìž…ë ¥í•˜ì‹  ì •ë³´ê°€ ì—†ìŠµë‹ˆë‹¤</td></tr>';
    }
    echo $itemList;
}elseif ($cmd == 'selectItemValue') {
    $menuid = post('menuid');

    $item_type_qry = exec_query_utf8("SELECT * FROM tblitemtype WHERE id=$menuid AND active=1");
    $itemTypeList = '';
    while ($item_type_row = mysqli_fetch_assoc($item_type_qry)) {
        $itemTypeList .= '<input type="hidden" value="'.$item_type_row['name'].'" id="hiddendValueItem" />';
        
    }
    echo $itemTypeList;
} elseif ($cmd == 'addNewMenuItem_inup') {
    $newMenuItemData = $_POST['newMenuItemData'];
    extract($newMenuItemData);
    $datetime = date("Y-m-d H:i:s");
    $quantity = $newItemMinQuantity . ',' . $newItemMaxQuantity . ',' . $newItemInterval;

    $msg = '';
    $returnData = '';
    if ($editMenuItemid > 0 and mysqli_num_rows(exec_query_utf8("SELECT id FROM tblitem WHERE type=$menuid AND itemName='$newItemName' AND id<>$editMenuItemid")) == 0) {
        exec_query_utf8("UPDATE tblitem SET itemName='$newItemName',type=$menuid,quantity='$quantity',price=$newItemPrice,des='" . addslashes($newMenuItemDes) . "',datetime='$datetime',active=1 WHERE id=$editMenuItemid LIMIT 1");
        $msg = 'updated';
    } elseif (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblitem WHERE type=$menuid AND itemName='$newItemName'")) == 0) {
        if (trim($newItemName) <> '' and $menuid > 0 and $newItemPrice > 0) {
            exec_query_utf8("INSERT INTO tblitem SET itemName='$newItemName',type=$menuid,quantity='$quantity',price=$newItemPrice,des='" . addslashes($newMenuItemDes) . "',datetime='$datetime'");
			$msg = 'inserted';
			
			// select id item to put in tblphotogallary
			
			/*$item_qry = exec_query_utf8("SELECT * FROM tblitem WHERE type=$menuid LIMIT 1");
			while ($item_row = mysqli_fetch_assoc($item_qry)) {
				$itemId = $item_row['id'];
			}
			
			
			$file = new \stdClass();
			$file->name = $this->get_file_name($uploaded_file, $name, $size, $type, $error,
				$index, $content_range);
			$file->size = $this->fix_integer_overflow(intval($size));
			$file->type = $type;
			if ($this->validate($uploaded_file, $file, $error, $index)) {
				$this->handle_form_data($file, $index);
				$upload_dir = $this->get_upload_path();
				if (!is_dir($upload_dir)) {
					mkdir($upload_dir, $this->options['mkdir_mode'], true);
				}
				$file_path = $this->get_upload_path($file->name);
				$append_file = $content_range && is_file($file_path) &&
					$file->size > $this->get_file_size($file_path);
				if ($uploaded_file && is_uploaded_file($uploaded_file)) {
					// multipart/formdata uploads (POST method uploads)
					if ($append_file) {
						file_put_contents(
							$file_path,
							fopen($uploaded_file, 'r'),
							FILE_APPEND
						);
					} else {
						//database
						$datetime = date("Y-m-d H:i:s");
						$cateid=$_REQUEST['menuidList'];
						/*$cateid=$_REQUEST['photoCateid'];
						$rowid=$_REQUEST['campingid'];
						if(singleCell_qry("code","tblcategory","id=$cateid LIMIT 1")=='Camping Each Room'){
							$rowid=$_REQUEST['eachRoomid'];
						}elseif(singleCell_qry("code","tblcategory","id=$cateid LIMIT 1")=='Camping Room Type'){
							$rowid=$_REQUEST['roomTypeid'];
						}
						exec_query_utf8("INSERT INTO tblphotogallery SET cateid=$cateid ,rowid=$itemId,filename='".$file->name."',description='',uploadDate='$datetime'");
						move_uploaded_file($uploaded_file, $file_path);
					}
				} else {
					// Non-multipart uploads (PUT method support)
					file_put_contents(
						$file_path,
						fopen('php://input', 'r'),
						$append_file ? FILE_APPEND : 0
					);
				}
				$file_size = $this->get_file_size($file_path, $append_file);
				if ($file_size === $file->size) {
					$file->url = $this->get_download_url($file->name);
					if ($this->is_valid_image_file($file_path)) {

						$this->handle_image_file($file_path, $file);
					}
				} else {
					$file->size = $file_size;
					if (!$content_range && $this->options['discard_aborted_uploads']) {
						unlink($file_path);
						$file->error = $this->get_error_message('abort');
					}
				}
				$this->set_additional_file_properties($file);
			}
			return $file;*/
		
            
        } else {
            $msg = 'invalid';
        }
    } else {
        if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblitem WHERE type=$menuid AND itemName='$newItemName' AND active=0")) > 0) {
            exec_query_utf8("UPDATE tblitem SET quantity='$quantity',price=$newItemPrice,des='" . addslashes($newMenuItemDes) . "',datetime='$datetime',active=1 WHERE type=$menuid AND itemName='$newItemName' LIMIT 1");
            $msg = 'updated';
        } else {
            $msg = 'exist';
        }
    }

    $data = array('msg' => $msg, 'data' => $returnData);
    echo json_encode($data);
} elseif ($cmd == 'deleteMenuItem_del') {
    $menuItemid = post('menuItemid');
    exec_query_utf8("UPDATE tblitem SET active=0 WHERE id=$menuItemid LIMIT 1");
    echo '';
} elseif ($cmd == 'loadMenuItemData') {
    $id = post('id');

    $data = array();
    $item_qry = exec_query_utf8("SELECT * FROM tblitem WHERE id=$id LIMIT 1");
    while ($item_row = mysqli_fetch_assoc($item_qry)) {
        $quantity = explode(',', $item_row['quantity']);
        $data = array('itemName' => $item_row['itemName'], 'minQ' => $quantity[0], 'maxQ' => $quantity[1], 'interval' => $quantity[2], 'price' => $item_row['price'], 'des' => $item_row['des']);
    }
    echo json_encode($data);
} elseif ($cmd == 'getUploadedImages') {
    $campingid = post('campingid');
    $photoCateid = post('photoCateid');
    $roomTypeid = post('roomTypeid');
    $eachRoomid = post('eachRoomid');

    $rowid = $campingid;
    if (singleCell_qry("code", "tblcategory", "id=$photoCateid LIMIT 1") == 'Camping Each Room') {
        $rowid = $eachRoomid;
    } elseif (singleCell_qry("code", "tblcategory", "id=$photoCateid LIMIT 1") == 'Camping Room Type') {
        $rowid = $roomTypeid;
    }

    $imgList = '';
    $img_short_dir = '/uploadedimages/files/';
    $img_dir = '/admin/uploadedimages/files/';
    $thumbnail_url = '/admin/uploadedimages/files/thumbnail/';
    $img_qry = exec_query_utf8("SELECT * FROM tblphotogallery WHERE cateid=$photoCateid AND rowid=$rowid ORDER BY id DESC");
    while ($img_row = mysqli_fetch_assoc($img_qry)) {
        $imgList .= '<tr class="template-download fade in">
						<td>
							<a href="' . $img_dir . $img_row['filename'] . '" title="' . $img_row['filename'] . '" download="' . $img_row['filename'] . '" data-gallery="">
								<div style="background:url(' . $thumbnail_url . $img_row['filename'] . ') center center no-repeat; background-size:cover; width:150px; height:150px;"></div>
							</a>
						</td>
						<td><a href="' . $img_dir . $img_row['filename'] . '" title="' . $img_row['filename'] . '" download="' . $img_row['filename'] . '" data-gallery="">' . $img_row['filename'] . '</a></td>
						<td>' . filesize_formatted('..' . $img_short_dir . $img_row['filename']) . '</td>
						<td>
							<button class="btn btn-danger delete" data-type="DELETE" data-url="/admin/uploadedimages/?file=' . $img_row['filename'] . '">
								<i class="glyphicon glyphicon-trash"></i>
								<span>Delete</span>
							</button>		
							<input type="checkbox" name="delete" value="1" class="toggle">
						</td>
					</tr>';
    }
    echo $imgList;
} elseif ($cmd == 'forumTopicList') {
    $keyword = post("keyword");
    $cateid = post('cateid');
    $currentPage = intval(post("currentPage"));
    $rowsPerPage = post("rowsPerPage");
    $navAction = post("navAction");

    //get forum id from tblcategoryType
    $typeid = singleCell_qry("id", "tblcategorytype", "typeCode='forum' AND active=1 LIMIT 1");

    $sql_condition = 'WHERE';
    if ($typeid > 0 and $typeid <> '') {
        $sql_condition.=" postType=$typeid";

    }
    if ($cateid > 0) {
        $sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " postCategory=$cateid";
    }
    if ($keyword <> '') {
        $sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " (title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
    }
    if ($sql_condition == 'WHERE') {
        $sql_condition = '';
    }
    //work with total page
    $navRow_qry = exec_query_utf8("SELECT * FROM tblposting $sql_condition ORDER BY id DESC");
    $totalRow = mysqli_num_rows($navRow_qry);
    $totalPages = $totalRow / $rowsPerPage;
    if ($totalRow % $rowsPerPage > 0) {
        $totalPages = intval($totalPages) + 1;
    }

    //get the target page number	
    $targetPage = 1;
    $nav_btn_disable = array();
    if ($navAction == 'first') {
        $targetPage = 1;
    } elseif ($navAction == 'prev') {
        $targetPage = $currentPage - 1;
    } elseif ($navAction == 'next') {
        $targetPage = $currentPage + 1;
    } elseif ($navAction == 'last') {
        $targetPage = $totalPages;
    } elseif ($navAction == 'goto') {
        $targetPage = $currentPage;
    }
    //get goto select list
    $gotoSelectNum = array();
    for ($i = 1; $i <= $totalPages; $i++) {
        $gotoSelectNum[] = $i;
    }

    if ($totalPages == 1 or $totalPages == 0) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 0, 'nav_last' => 0);
    } elseif ($targetPage == 1) {
        $nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 1, 'nav_last' => 1);
    } elseif ($targetPage == $totalPages) {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 0, 'nav_last' => 0);
    } else {
        $nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 1, 'nav_last' => 1);
    }

    $startIndex = ($targetPage - 1) * $rowsPerPage;
    $forumTopicListString = '';
    $i = $startIndex + 1;
    $forumTopic_qry = exec_query_utf8("SELECT * FROM tblposting $sql_condition ORDER BY id DESC LIMIT " . $startIndex . ",$rowsPerPage");
    while ($forumTopic_row = mysqli_fetch_assoc($forumTopic_qry)) {
        $active = $forumTopic_row['active'];
        $postedBy = 'N/A';
        $postedBy_qry = exec_query_utf8("SELECT firstName,lastName FROM tbluser WHERE id=" . $forumTopic_row['userid'] . " LIMIT 1");
        while ($postedBy_row = mysqli_fetch_assoc($postedBy_qry)) {
            $postedBy = ucfirst($postedBy_row['firstName']) . ' ' . ucfirst($postedBy_row['lastName']);
        }

        $totalComments = mysqli_num_rows(exec_query_utf8("SELECT * FROM tblcomment WHERE postid=" . $forumTopic_row['id'] . " AND active=1"));

        $forumTopicListString .= '<tr>
									<td class="tableCellCenter" style="' . ($active == 0 ? 'border-left:3px solid #ec6513;' : '') . '">' . $i . '</td>                                    
                                    <td class="tableCellLeftCenter">
									<div class="list_top_small_txt"><i class="fa fa-user fa-fw"></i> ' . ($postedBy == '' ? 'Admin' : $postedBy) . ' | <i class="fa fa-comments fa-fw"></i> ' . $totalComments . ' | <i class="fa fa-clock-o fa-fw"></i> ' . date('d/m/Y H:i A', strtotime($forumTopic_row['datetime'])) . '</div>
									<div>' . $forumTopic_row['title'] . '</div>
									
									</td>
									<td class="tableCellCenter action_td_verticle">
										<div><button class="btn btn-primary btn-xs" onclick="loadForumComment(' . $forumTopic_row['id'] . ')"><i class="fa fa-eye fa-fw"></i> Read</button></div>
										<div><button class="btn btn-primary btn-xs" style="' . ($active == 0 ? 'background:#ec6513;' : '') . '" onclick="comfirmDeactivateTopic(' . $forumTopic_row['id'] . ',' . $active . ')">' . ($active == 1 ? '<i class="fa fa-lock fa-fw"></i> Deactivate' : '<i class="fa fa-unlock-alt fa-fw"></i> Activate') . '</button></div>
									</td>
                               </tr>';
        $i++;
    }
    if ($forumTopicListString == '') {
        $forumTopicListString = '<tr><td colspan="3" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> No Topic Found</td></tr>';
    }
    $data = array('list' => $forumTopicListString, 'targetPage' => $targetPage, 'totalPages' => $totalPages, 'gotoSelectNum' => $gotoSelectNum, 'nav_btn_disable' => $nav_btn_disable);

    echo json_encode($data);
} elseif ($cmd == 'deactivateForumTopic_block') {
    $id = post('id');

    $camp_qry = exec_query_utf8("UPDATE tblposting SET active = NOT active WHERE id=$id LIMIT 1");
} elseif ($cmd == 'loadForumComment') {
    $id = post('id');
    $totalRowstoShow = 5;
    $totalRows = mysqli_num_rows(exec_query_utf8("SELECT * FROM tblcomment WHERE postid=$id AND active=1"));

    $data = array();
    $posting_qry = exec_query_utf8("SELECT * FROM tblposting WHERE id=$id AND active=1 LIMIT 1");
    while ($posting_row = mysqli_fetch_assoc($posting_qry)) {
        $postedBy = 'N/A';
        $postedBy_qry = exec_query_utf8("SELECT firstName,lastName FROM tbluser WHERE id=" . $posting_row['userid'] . " LIMIT 1");
        while ($postedBy_row = mysqli_fetch_assoc($postedBy_qry)) {
            $postedBy = ucfirst($postedBy_row['firstName']) . ' ' . ucfirst($postedBy_row['lastName']);
        }

        $data = array('title' => $posting_row['title'], 'totalComments' => $totalRows, 'user' => ($postedBy == '' ? 'Admin' : $postedBy), 'description' => $posting_row['description'], 'datetime' => date('d/m/Y H:i A', strtotime($posting_row['datetime'])));
    }

    $comments = array();
    $lastrowid = 0;
    $comment_qry = exec_query_utf8("SELECT * FROM tblcomment WHERE postid=$id AND active=1 ORDER BY id DESC LIMIT $totalRowstoShow");
    while ($comment_row = mysqli_fetch_assoc($comment_qry)) {
        $commentUser = singleCell_qry("firstName", 'tbluser', ' id=' . $comment_row['userid'] . ' AND active=1 LIMIT 1');
        $comments[] = array('id' => $comment_row['id'], 'username' => ($commentUser == '' ? 'N/A' : $commentUser), 'comment' => $comment_row['comment'], 'datetime' => date('d/m/Y H:i A', strtotime($comment_row['datetime'])));
        $lastrowid = $comment_row['id'];
    }
    $data['comments'] = $comments;
    $data['lastid'] = $lastrowid;
    $data['loadMore'] = ($totalRows > $totalRowstoShow ? '1' : '0');
    echo json_encode($data);
} elseif ($cmd == 'loadOlderComment') {
    $postid = post('postid');
    $lastid = post('lastid');
    $totalRowstoShow = 5;

    $comments = array();
    $lastrowid = 0;
    $comment_qry = exec_query_utf8("SELECT * FROM tblcomment WHERE postid=$postid AND id<$lastid AND active=1 ORDER BY id DESC LIMIT $totalRowstoShow");
    while ($comment_row = mysqli_fetch_assoc($comment_qry)) {
        $commentUser = singleCell_qry("firstName", 'tbluser', ' id=' . $comment_row['userid'] . ' AND active=1 LIMIT 1');
        $comments[] = array('id' => $comment_row['id'], 'username' => ($commentUser == '' ? 'N/A' : $commentUser), 'comment' => $comment_row['comment'], 'datetime' => date('d/m/Y H:i A', strtotime($comment_row['datetime'])));
        $lastrowid = $comment_row['id'];
    }

    $lastidOfTotalRows = singleCell_qry("id", "tblcomment", "postid=$postid AND active=1 ORDER BY id ASC LIMIT 1");

    $data = array('lastid' => $lastrowid, 'comments' => $comments, 'loadMore' => ($lastrowid > $lastidOfTotalRows ? '1' : '0'));
    echo json_encode($data);
} elseif ($cmd == 'deleteComment_del') {
    $id = post('id');

    $camp_qry = exec_query_utf8("UPDATE tblcomment SET active = NOT active WHERE id=$id LIMIT 1");
} elseif ($cmd == 'updateMessageTemplate_inup') {
    $templateData = $_POST['templateData'];
    extract($templateData);
    $datetime = date("Y-m-d H:i:s");
    $lastid = 0;

    if ($templateid == 0) {
        if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblmessagetemplate WHERE category=$categoryid AND type='$templateType'")) == 0) {
            exec_query_utf8("INSERT INTO tblmessagetemplate SET type='$templateType',category=$categoryid,title='$t_title',message='$t_message',callback='$t_callback',datetime='$datetime',active=" . ($t_active == 'true' ? 1 : 0));
            $lastid = mysqli_insert_id($conn);
        }
    } elseif ($templateid > 0) {
        exec_query_utf8("UPDATE tblmessagetemplate SET title='" . strip_tags(addslashes($t_title)) . "',message='" . addslashes($t_message) . "',callback='" . strip_tags(addslashes($t_callback)) . "',datetime='$datetime',active=" . ($t_active == 'true' ? 1 : 0) . " WHERE category=$categoryid AND type='$templateType' LIMIT 1");
    }
    echo $lastid;
} elseif ($cmd == 'saveRoomMap_inup') {
    $locationData = $_POST['locationData'];
    $room_id = 0;
    foreach ($locationData as $key => $value) {
        $locationStr = implode(',', $value);
        $id_parts = explode('_', $key);
        $rowid = $id_parts[2];
        $room_id = $rowid;
        exec_query_utf8("UPDATE tblcampingeachroom SET location = '$locationStr' WHERE id=$rowid LIMIT 1");
    }

    $visible_map = $_POST['visibleMap'];
    $camp_id = $_POST['id'];
    exec_query_utf8("UPDATE tblcamping SET visiblemap = $visible_map WHERE id = $camp_id ");
} elseif ($cmd == 'locateRoomPosition') {
    $campingid = post('campingid');

    $rooms = array();
    $room_qry = exec_query_utf8("SELECT * FROM tblcampingeachroom WHERE roomid IN (SELECT id FROM tblcampingroom WHERE campingid=$campingid AND active=1) ORDER BY id ASC");
    while ($room_row = mysqli_fetch_assoc($room_qry)) {
        $position = explode(',', $room_row['location']);
        //$rooms[] = array('id'=>$room_row['id'],'position'=>explode(',',$room_row['location']));	

        $useCustomName = $room_row['useCustomName'];
        $typeid = $room_row['roomid'];
        $roomLabel = '<div class="room_name">' . ($useCustomName == 1 ? ucfirst($room_row['customName']) : singleCell_qry("roomName", "tblcampingroom", "id=" . $room_row['roomid'] . " LIMIT 1") . " ") . $room_row['roomNumber'] . '</div>';
        $rooms[$room_row['id']] = array('typeid' => $typeid, 'position' => $position, 'roomLabel' => $roomLabel, 'active' => $room_row['active']);
    }

    $imgType = 'Camping Static Map';
    $img_url = '/admin/uploadedimages/files/';
    $filename = singleCell_qry("filename", "tblphotogallery", "cateid=(SELECT id FROM tblcategory WHERE code='$imgType' LIMIT 1) AND rowid=$campingid ORDER BY uploadDate DESC LIMIT 1");

    if ($filename == '') {
        $mapbg = singleCell_qry("mapCoordinate", "tblcamping", "id=$campingid LIMIT 1");
        $coord_parts = explode(',', $mapbg);
        $mapImg = 'http://maps.googleapis.com/maps/api/staticmap?center=' . $coord_parts[0] . ',' . $coord_parts[1] . '&zoom=' . str_ireplace('z', '', $coord_parts[2]) . '&size=600x600';
    } else {
        $mapImg = $img_url . $filename;
    }

    $data = array('mapImg' => $mapImg, 'rooms' => $rooms);
    echo json_encode($data);
} elseif ($cmd == 'loadHolidayList') {
    if (isset($_SESSION['adminid'])) {
        $comid = $_POST['comid'];
    } else {
        $comid = $company_sessionid;
    }
    $type = $_POST['type'];

    $moreCond = '';
    if ($type > 0) {
        $moreCond = "AND id=$type ";
    }
    //occasion type
    $holidayList = array();
    $occasionType_qry = exec_query_utf8("SELECT * FROM tbloccasiontype WHERE autoSet=0 $moreCond AND active=1 ORDER BY id ASC");
    while ($occasionType_row = mysqli_fetch_assoc($occasionType_qry)) {
        $typeid = $occasionType_row['id'];
        $eachType = array();
        $manualHoliday_qry = exec_query_utf8("SELECT * FROM tblmanualhighdate WHERE comid=$comid AND type=$typeid AND active=1 ORDER BY type");
        if (mysqli_num_rows($manualHoliday_qry) == 0) {
            $stdHoliday_qry = exec_query_utf8("SELECT * FROM tblstdhighdate WHERE type=$typeid AND active=1 ORDER BY type");
            while ($stdHoliday_row = mysqli_fetch_assoc($stdHoliday_qry)) {
                $holidayType = $stdHoliday_row['type'];
                $fromDate = $stdHoliday_row['fromDate'];
                $toDate = $stdHoliday_row['toDate'];
                $description = $stdHoliday_row['description'];
                $todayDate = date("Y-m-d H:i:s");
                exec_query_utf8("INSERT INTO tblmanualhighdate SET comid=$comid,type=$holidayType,fromDate='$fromDate',toDate='$toDate',description='$description',updateDate='$todayDate'");
                $manualHoliday_qry = exec_query_utf8("SELECT * FROM tblmanualhighdate WHERE comid=$comid AND type=$typeid AND active=1 ORDER BY type");
            }
        }

        while ($manualHoliday_row = mysqli_fetch_assoc($manualHoliday_qry)) {
            $holidayid = $manualHoliday_row['id'];
            $holidayType = $manualHoliday_row['type'];
            $fromDate = $manualHoliday_row['fromDate'];
            $toDate = $manualHoliday_row['toDate'];
            $description = $manualHoliday_row['description'];

            $eachType[] = array('from' => $fromDate, 'to' => $toDate, 'des' => $description, 'tools' => '<i class="fa fa-pencil-square-o fa-fw"></i> <span onClick="comfirmDeleteHolidayDate(' . $holidayid . ')" class="icon_btn"><i class="fa fa-times-circle-o fa-fw"></i></span>');
        }
        $holidayList[$typeid] = $eachType;
    }
    echo json_encode($holidayList);
} elseif ($cmd == 'addDateData_inup') {
    if (isset($_SESSION['adminid'])) {
        $comid = $_POST['comid'];
    } else {
        $comid = $company_sessionid;
    }
    $type = $_POST['type'];
    $fromDate = date("Y-m-d", strtotime($_POST['fromDate']));
    $toDate = date("Y-m-d", strtotime($_POST['toDate']));
    $description = $_POST['description'];
    $todayDate = date("Y-m-d H:i:s");

    if ($comid == 0) {
        echo json_encode(array('result' => 0, 'msg' => 'invaid company id'));
        exit;
    }

    //get id by type
    $idByType = array();
    $occasionType_qry = exec_query_utf8("SELECT * FROM tbloccasiontype WHERE occasionType=(SELECT occasionType FROM tbloccasiontype WHERE id=$type) AND active=1");
    while ($occasionType_row = mysqli_fetch_assoc($occasionType_qry)) {
        $idByType[] = $occasionType_row['id'];
    }

    $idByType = implode(',', $idByType);

    $checkOverlap = exec_query_utf8("SELECT * FROM tblmanualhighdate WHERE comid=$comid AND type IN ($idByType) AND '$fromDate' <= toDate AND '$toDate' >= fromDate");

    if (mysqli_num_rows($checkOverlap) == 0) {
        exec_query_utf8("INSERT INTO tblmanualhighdate SET comid=$comid,type=$type,fromDate='$fromDate',toDate='$toDate',description='$description',updateDate='$todayDate'");
        $result = array('result' => 1, 'msg' => 'added');
    } else {
        $result = array('result' => 0, 'msg' => 'overlap');
    }
    echo json_encode($result);
} elseif ($cmd == 'deleteHolidayDate_del') {
    $id = $_POST['id'];
    $typeid = singleCell_qry("type", "tblmanualhighdate", "id=$id LIMIT 1");
    exec_query_utf8("DELETE FROM tblmanualhighdate WHERE id=$id");
    echo $typeid;
} elseif ($cmd == 'reservationList') {
	


    //$keyword = post("keyword");
    if (isset($_SESSION['adminid'])) {
        $companyid = post("companyid");
    } else {
        $companyid = $company_sessionid;
    }
	
	$campingType = post('campingType');
    $activeStatus = post('activeStatus');
	$optPending = post('optPending');
    $currentPage = intval(post("currentPage"));
    $rowsPerPage = post("rowsPerPage");
    $navAction = post("navAction");
    $username = post('username');
	$phoneNumber = post('phoneNumber');
	$searchPaymentmethod = post('paymentmethod');
    
//    $dataIn = date("Y-m-d", strtotime(post("checkinDate")));
//    $dataOut = date("Y-m-d", strtotime(post("checkoutDate")));
//    
    $dataIn = post('checkinDate');
    $dataOut = post('checkoutDate');
    $mydatein = new DateTime($dataIn);
    $mydateOut = new DateTime($dataOut);
    $myDateNow = date("Y-m-d");
	$dateSearchBetween = post('dateSearchBetween');
	$myDateSearchBetween = new DateTime($dateSearchBetween);
    $sql_condition = "WHERE";
 
	// DEATIVATE RESERVATION WITH PAYMENT STATUS = NOT YET PAID AFTER 3 MINUTES OF TRANSACTION ( IN CASE CLIENT CANCEL KCP PAMENT FORM ) 
	//exec_query_utf8("UPDATE tblbooking SET active = 0, other='set active = 0 due to kcp take too long time' WHERE ADDTIME( bookingDate , SEC_TO_TIME( 5*60 ) ) < NOW()  AND  pending =1  AND  (paymentmethod='신용카드' OR paymentmethod='계좌이체' OR paymentmethod='포인트') ");
	
	$result_for_cancel = exec_query_utf8("SELECT id, userid FROM tblbooking WHERE ADDTIME( bookingDate , SEC_TO_TIME( 1*60 ) ) < NOW()  AND  pending =1  AND  (paymentmethod='신용카드' OR paymentmethod='계좌이체' OR paymentmethod='포인트')");
	while( $row_for_cancel = mysqli_fetch_object( $result_for_cancel)){
		$record_cancel_id = $row_for_cancel->id;
		$record_cancel_user_id = $row_for_cancel->userid;
		
		$time=time();
		$time_check=$time-60; 
		if (mysqli_num_rows(exec_query_utf8("SELECT id FROM tblonline WHERE userid = $record_cancel_user_id AND active=1 AND timestamp > $time_check ORDER BY id DESC ")) == 0 ){
			exec_query_utf8("UPDATE tblbooking SET active = 0, other='set active = 0 due to kcp take too long time' WHERE id = $record_cancel_id");
		}
	}

	
    //if($companyid==0 or $companyid==''){$companyid=$company_sessionid;}
   
   
	$long_time = 12; // its contents were 1, 2, 3, 4, and so on
	$long_time_sec =$long_time * 3600; // $long_time * 3600 convert hours to seconds
		
	$qryAllReservationHistory = exec_query_utf8("SELECT * FROM `tblbooking` where active = 1 AND paymentmethod = '무통장결제'"); 
	while ($booking_reservation_row = mysqli_fetch_assoc($qryAllReservationHistory)) {
		
		$allbookingid = $booking_reservation_row['id']; 
		 
		$check_sent_cancel_sms = false;
		
		/* Start condition time over 12 hours */
		$date_delaypayment = $booking_reservation_row['delaypaymentdate'];
		$cancel_delaypayment_date = strtotime($date_delaypayment) + $long_time_sec;
		
		$date_booking = $booking_reservation_row['bookingDate']; // need to be formatted as "YYYY-MM-DD HH:II:SS" 
		$cancel_booking_date = strtotime($date_booking) + $long_time_sec;
		 
		$currentDateTime = date('Y-m-d H:i:s');
		
		// pending = 0 , sms = 3 and delaypayment date over 12 hr
		if($booking_reservation_row['pending']==0 and $booking_reservation_row['sms_auto_cancel']==0){
			if(date('Y-m-d H:i:s',$cancel_delaypayment_date) < $currentDateTime){
				exec_query_utf8("UPDATE tblbooking SET pending = 3, sms_auto_cancel = 1 WHERE id = $allbookingid");
				$check_sent_cancel_sms = true;
			}
		}
		// pending = 1 , sms = 3 and booking date or delay date over 12 hr
		else if($booking_reservation_row['pending']==1 and $booking_reservation_row['sms_auto_cancel']==0){
			if($booking_reservation_row['delaypaymentdate'] != "0000-00-00 00:00:00"){
				// compair with delay payment date
				if(date('Y-m-d H:i:s',$cancel_delaypayment_date) < $currentDateTime){
					exec_query_utf8("UPDATE tblbooking SET pending = 3, sms_auto_cancel = 1 WHERE id = $allbookingid");
					$check_sent_cancel_sms = true;
				}
			}else if($booking_reservation_row['bookingDate'] != "0000-00-00 00:00:00"){
				// compair with booking date
				if(date('Y-m-d H:i:s',$cancel_booking_date) < $currentDateTime){
					exec_query_utf8("UPDATE tblbooking SET pending = 3, sms_auto_cancel = 1 WHERE id = $allbookingid");
					$check_sent_cancel_sms = true;
				}	
			}
		}
		
		if ( $check_sent_cancel_sms ){
			
			//.....................................................................................
			//IN CASE, AUTOMATICALLY CANCEL OF RESERVATION
			//.....................................................................................
			
			$result_sms = exec_query_utf8("SELECT * FROM tblmessagetemplate WHERE title = 'auto cancel'");
			$row_sms = mysqli_fetch_object($result_sms);
			
			//.............plese fix this value
			$clientid = $booking_reservation_row['userid'];
			
			$client_name = $booking_reservation_row['username'];
			$client_phone = $booking_reservation_row['phoneNumber'];
			
			//.............plese fix this value
			$room_type_id = $booking_reservation_row['roomType'];
			//.............please fix this value
			$cin = $booking_reservation_row['checkinDate'];
			$cout = $booking_reservation_row['checkoutDate'];

			$date1 = new DateTime($cin);
			$date2 = new DateTime($cout);
			
			$diff = $date2->diff($date1)->format("%a");
		
			$booking_period = $diff. '박';
			
			$result_company = exec_query_utf8("SELECT companyName, roomName, companyPhone FROM tbluser user INNER JOIN tblcamping camping ON user.id = camping.userid INNER JOIN tblcampingroom campingroom ON campingroom.campingid = camping.id WHERE campingroom.id = ". $room_type_id );
			$row_company = mysqli_fetch_object($result_company);
			
			$company_name = $row_company->companyName;
			$room_type_name = $row_company->roomName;
			$glamping_company_name = $company_name;
			
			$company_phone = str_replace(array("-"," "), "", $row_company->companyPhone);
			
			//%예약자명<1>%님 %시작일<6>%(%예약기간<10>%)/%객실명<4>%예약 미입금으로 자동취소되었습니다.
			$message_to_company = $row_sms->messagetocompany;
			$message_to_company = str_replace(
									array("%예약자명<1>%","%시작일<6>%","%예약기간<10>%","%객실명<4>%"),
									array( $client_name, $cin, $booking_period, $room_type_name),
									$message_to_company);
									
			$message_to_company .= "(".$client_phone.")";
									
			//%예약자명<1>%님 %시작일<6>%(%예약기간<10>%)/%객실명<4>%예약 미입금으로 자동취소되었습니다.
			$message_to_client = $row_sms->message;
			$message_to_client = str_replace( 
					array("%예약자명<1>%"," %시작일<6>%","%예약기간<10>%","%객실명<4>%", "%업체명<8>%"), 
					array( $client_name, $cin, $booking_period, $room_type_name , $glamping_company_name ), 
					$message_to_client
					);
			if ( $row_sms->active == 1 ){
				// sending message to client by glamping company
				sendSMS_05_mar_15($message_to_client, $client_name, $client_phone, $row_sms->callback==''? $company_phone:str_replace(array("-"," "), "", $row_sms->callback) );
				// sending message to glamping company by client
				sendSMS_05_mar_15($message_to_company, $company_name, $company_phone , $default_sms_sender );
				
				//sent email to admin
				booking_mail ('(Auto Cancel by admin open(id:'.$allbookingid.'))'.$message_to_client);
				booking_mail ('(Auto Cancel by admin open(id:'.$allbookingid.'))'.$message_to_company, 2);
			}
			
				
		}
		/* end condition time over 12 hours */
	}
	
	$sql_condition = 'where active=1';
	if ($companyid > 0) {
		$sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " roomNumberid IN (SELECT id FROM tblcampingeachroom WHERE roomid IN (SELECT id FROM tblcampingroom WHERE campingid IN (SELECT id FROM tblcamping WHERE userid=$companyid)))";
	}
	//if($keyword<>''){$sql_condition.=($sql_condition=='WHERE'?'':' AND')." id LIKE '%$keyword%'";}
	if ($campingType <> 'all') {
		$sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " roomNumberid IN (SELECT id FROM tblcampingeachroom WHERE roomid IN (SELECT id FROM tblcampingroom WHERE campingid IN (SELECT id FROM tblcamping WHERE campingCategory = $campingType)))";
	}
	if ($activeStatus <> 'all') {
		$sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " active=$activeStatus";
	}
	if ($optPending <> 'all') {
		$sql_condition.=($sql_condition == 'WHERE' ? '' : ' AND') . " pending=$optPending";
	}
	// add new condiction
	if ($dataIn && $dataOut){
		$sql_condition .= " AND checkinDate >= '".$mydatein->format('Y-m-d')."' AND checkoutDate <= '".$mydateOut->format('Y-m-d')."'";
	}elseif($dataIn){
		$sql_condition .= " AND checkinDate = '".$mydatein->format('Y-m-d')."'";
	}elseif($dataOut){
		$sql_condition .= " AND checkoutDate = '".$mydateOut->format('Y-m-d')."'";
	}elseif($dateSearchBetween){
		$sql_condition .= " AND checkinDate <= '".$myDateSearchBetween->format('Y-m-d')."' AND checkoutDate > '".$myDateSearchBetween->format('Y-m-d')."'";
	} 
	
	if($username) {
		$sql_condition .= " AND username LIKE '%$username%'";
	}
	if($phoneNumber) {
		$sql_condition .= " AND phoneNumber LIKE '%$phoneNumber%'";
	}
	
	$pay = explode(',', $searchPaymentmethod);
	
	$numResBy = 0;
	for($numMethod = 0; $numMethod < 6; $numMethod ++){
		if( $pay[$numMethod] == 2 || $pay[$numMethod] == 3 ){
			if($numResBy > 0){ 
				$sql_condition .= " OR reservation_by LIKE '%$pay[$numMethod]%'";	
			}else{
				$sql_condition .= " AND reservation_by LIKE '%$pay[$numMethod]%'"; 
			} 
			$numResBy++;   
		}
		
		if($pay[$numMethod] && $pay[$numMethod] != 2 && $pay[$numMethod] != 3){
			if($numMethod > 0){
				$sql_condition .= " OR paymentmethod LIKE '%$pay[$numMethod]%'";
			}else{
				$sql_condition .= " AND paymentmethod LIKE '%$pay[$numMethod]%'";
			}
		}
	}
	
	// end new condiction
	
	if ($sql_condition == 'WHERE') {
		$sql_condition = '';
	}
	
	if(isset($_SESSION['adminid'])){
		$navRow_qry = exec_query_utf8("SELECT * FROM tblbooking $sql_condition AND visible =1  ORDER BY bookingDate DESC "); 
	}elseif(isset($_SESSION['userid'])){
		$Use_id = decodeString($_SESSION['userid'],$encryptKey) ;
		$navRow_qry = exec_query_utf8("SELECT * FROM tblbooking $sql_condition AND visible =1  AND roomNumberid IN (SELECT id FROM tblcampingeachroom WHERE roomid IN (SELECT id FROM tblcampingroom WHERE campingid IN (SELECT id FROM tblcamping WHERE userid=$Use_id))) ORDER BY bookingDate DESC ");
	}
	
	$totalRow = mysqli_num_rows($navRow_qry);
	$totalPages = $totalRow / $rowsPerPage;

	if ($totalRow % $rowsPerPage > 0) {
		$totalPages = intval($totalPages) + 1;
	}

	//get the target page number	
	$targetPage = 1;
	$nav_btn_disable = array();
	if ($navAction == 'first') {
		$targetPage = 1;
	} elseif ($navAction == 'prev') {
		$targetPage = $currentPage - 1;
	} elseif ($navAction == 'next') {
		$targetPage = $currentPage + 1;
	} elseif ($navAction == 'last') {
		$targetPage = $totalPages;
	} elseif ($navAction == 'goto') {
		$targetPage = $currentPage;
	}
	//get goto select list
	$gotoSelectNum = array();
	for ($i = 1; $i <= $totalPages; $i++) {
		$gotoSelectNum[] = $i;
	}

	if ($totalPages == 1 or $totalPages == 0) {
		$nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 0, 'nav_last' => 0);
	} elseif ($targetPage == 1) {
		$nav_btn_disable = array('nav_first' => 0, 'nav_prev' => 0, 'nav_next' => 1, 'nav_last' => 1);
	} elseif ($targetPage == $totalPages) {
		$nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 0, 'nav_last' => 0);
	} else {
		$nav_btn_disable = array('nav_first' => 1, 'nav_prev' => 1, 'nav_next' => 1, 'nav_last' => 1);
	}

	$startIndex = ($targetPage - 1) * $rowsPerPage;
	$reservationListString = '';
	$i = $startIndex + 1;
	//AND ( paymentmethod='신용카드' OR paymentmethod='계좌이체' )
	
	if(isset($_SESSION['adminid'])){
		$reservation_qry = exec_query_utf8("SELECT * FROM tblbooking  $sql_condition  AND visible =1   ORDER BY bookingDate DESC LIMIT " . $startIndex . ",$rowsPerPage");
	}elseif(isset($_SESSION['userid'])){
		$Use_id = decodeString($_SESSION['userid'],$encryptKey) ;
		$reservation_qry = exec_query_utf8("SELECT * FROM tblbooking  $sql_condition  AND visible =1  AND roomNumberid IN (SELECT id FROM tblcampingeachroom WHERE roomid IN (SELECT id FROM tblcampingroom WHERE campingid IN (SELECT id FROM tblcamping WHERE userid=$Use_id))) ORDER BY bookingDate DESC LIMIT " . $startIndex . ",$rowsPerPage");
	}
	
    //  $navRow_qry = exec_query_utf8("SELECT * FROM tblbooking $sql_condition ORDER BY bookingDate DESC");
    //work with total page
	
    while ($booking_row = mysqli_fetch_assoc($reservation_qry)) {
        $bookingid = $booking_row['id'];
		$promotion_discount = $booking_row['promotion_discount'];
		$promotion_explode = explode(',',$booking_row['promotion_discount']);
		
		// start condition change background color of each row
        $style = 'background:rgba(63,188,62,0.74); color:#fff;';
		$style_tem = 'background:rgba(63,188,62,0.74); color:#fff;';
		
		$date_delaypayment = $booking_row['delaypaymentdate'];
		$cancel_delaypayment_date = strtotime($date_delaypayment) + $long_time_sec;
		
		$date_booking = $booking_row['bookingDate']; // need to be formatted as "YYYY-MM-DD HH:II:SS" 
		$cancel_booking_date = strtotime($date_booking) + $long_time_sec;
		 
		$currentDateTime = date('Y-m-d H:i:s');
		
		// pending = 0 , sms = 3 and delaypayment date over 12 hr
		if($booking_row['pending']==0 and $booking_row['sms_auto_cancel']==0){
			if(date('Y-m-d H:i:s',$cancel_delaypayment_date) < $currentDateTime){
				$style = 'background-color:#c4c4c4; color:#fff;';
			}
		}
		// pending = 1 , sms = 3 and booking date or delay date over 12 hr
		else if($booking_row['pending']==1 and $booking_row['sms_auto_cancel']==0){
			if($booking_row['delaypaymentdate'] != "0000-00-00 00:00:00"){
				// compair with delay payment date
				if(date('Y-m-d H:i:s',$cancel_delaypayment_date) < $currentDateTime){
					$style = 'background-color:#c4c4c4; color:#fff;';
				}
			}else if($booking_row['bookingDate'] != "0000-00-00 00:00:00"){
				// compair with booking date
				if(date('Y-m-d H:i:s',$cancel_booking_date) < $currentDateTime){
					$style = 'background-color:#c4c4c4; color:#fff;';
				}	
			}
		}
		/* End condition time over 12 hours */
		
		else if (($booking_row['pending']) == 3) {
            $style = 'background-color:#c4c4c4; color:#fff;';
        }else if (strtotime($booking_row['checkoutDate']) < time()) {
            $style = 'background-color:rgba(215,64,67,0.80); color:#fff;';
			$style_tem = 'background-color:rgba(215,64,67,0.80); color:#fff;';
        }  elseif (strtotime($booking_row['checkoutDate']) >= time() and strtotime($booking_row['checkinDate']) <= time()) {

            $style = 'background-color:rgba(224,196,80,0.79); color:#fff;';
			$style_tem = 'background-color:rgba(224,196,80,0.79); color:#fff;';
        }
		// end condition change background color of each row
		if ( $detect->isMobile() ) {
			$mobileStyle = "display:none;";
		}else{
			$mobileStyle = "";	
		}
		
		if (!isset($_SESSION['adminid'])){
			$styleAdmin = "display:none;";
		}else{
			$styleAdmin = "";
		}
		
		$reservationListString .= '<tr id="delete_row_'.$bookingid.'"  style="'.($booking_row['reservation_by']==2? "":"").'">
										<td class="tableCellCenter" id="charge_price_style_'.$bookingid.'" style="' .$styleAdmin.$mobileStyle. $style . '"><span style="display:none;'.$style_tem.'" id="schedule_row_tem_color_'.$bookingid.'"></span>' . $bookingid .'</td>
										<td>';

        $companyName = singleCell_qry("companyName", "tbluser", "id=(SELECT userid FROM tblcamping WHERE id=(SELECT campingid FROM tblcampingroom WHERE id=(SELECT roomid FROM tblcampingeachroom WHERE id=" . $booking_row['roomNumberid'] . " LIMIT 1) LIMIT 1) LIMIT 1) LIMIT 1");

        $campingType = singleCell_qry("categoryTitle", "tblcategory", "id=(SELECT campingCategory FROM tblcamping WHERE id=(SELECT campingid FROM tblcampingroom WHERE id=(SELECT roomid FROM tblcampingeachroom WHERE id=" . $booking_row['roomNumberid'] . " LIMIT 1) LIMIT 1) LIMIT 1) LIMIT 1");
        $campingTypeIcon = singleCell_qry("icon", "tblcategory", "id=(SELECT campingCategory FROM tblcamping WHERE id=(SELECT campingid FROM tblcampingroom WHERE id=(SELECT roomid FROM tblcampingeachroom WHERE id=" . $booking_row['roomNumberid'] . " LIMIT 1) LIMIT 1) LIMIT 1) LIMIT 1");

        $campingSubLocation = singleCell_qry("subLocationName", "tblsublocation", "id=(SELECT locationid FROM tblcamping WHERE id=(SELECT campingid FROM tblcampingroom WHERE id=(SELECT roomid FROM tblcampingeachroom WHERE id=" . $booking_row['roomNumberid'] . " LIMIT 1) LIMIT 1) LIMIT 1) LIMIT 1");
        $campingMainLocation = singleCell_qry("cityName", "tblmainlocation", "id=(SELECT cityid FROM tblsublocation WHERE id=(SELECT locationid FROM tblcamping WHERE id=(SELECT campingid FROM tblcampingroom WHERE id=(SELECT roomid FROM tblcampingeachroom WHERE id=" . $booking_row['roomNumberid'] . " LIMIT 1) LIMIT 1) LIMIT 1) LIMIT 1) LIMIT 1");

        $getRoomType = $booking_row['roomType'];
        $getRoomNumberid = $booking_row['roomNumberid'];
		$getPhoneNumber = $booking_row['phoneNumber'];
        $eachItem = extraItemDecode($booking_row['extraItem'], 1);
        $chechinDate = $booking_row['checkinDate'];
        $checkoutDate = $booking_row['checkoutDate'];
        $nightPrice = explode(',', $booking_row['nightPrice']);
        $itemPrice = explode(',', $booking_row['itemPrice']);
        $adult = $booking_row['adult'];
        $extraAdult = explode('-', $booking_row['extraAdult']);
        $children = $booking_row['children'];
        $extraChildren = explode('-', $booking_row['extraChildren']);
        //$totalAdult = $adult + intval($extraAdult[0]);

        $getItemPrice = array();
        foreach ($itemPrice as $key => $value) {
            $eachItemData = explode('-', $value);
            if (count($eachItemData) > 1) {
                $getItemPrice[$eachItemData[0]] = $eachItemData[1];
            }
        }

        $totalPrice = array_sum($nightPrice) + $extraAdult[1] + $extraChildren[1] + array_sum($getItemPrice);

        $campName = '';
        if (singleCell_qry("useCustomName", "tblcampingeachroom", "id=$getRoomNumberid LIMIT 1") == 1) {
            $roomNumber = singleCell_qry("customName", "tblcampingeachroom", "id=$getRoomNumberid LIMIT 1");
        } else {
            $roomNumber = 'Room ' . singleCell_qry("roomNumber", "tblcampingeachroom", "id=$getRoomNumberid LIMIT 1");
        }
        $roomType = singleCell_qry("roomName", "tblcampingroom", "id=$getRoomType LIMIT 1");

        $camp_qry = exec_query_utf8("SELECT * FROM tblcamping WHERE id=(SELECT campingid FROM tblcampingroom WHERE id=$getRoomType LIMIT 1) AND active=1 LIMIT 1");
        
        $userUsername = $booking_row['username'];
        $PhoneNumberUser = $booking_row['phoneNumber'];
        $EmailUser = $booking_row['bookemail'];
		$checkinDate = $booking_row['checkinDate'];
		$checkoutDate = $booking_row['checkoutDate'];
		$chargePrice = $booking_row['charge_price'];
        $chargeReason = $booking_row['charge_reason'];
        $paymentMethod = $booking_row['paymentmethod'];
		
		if($chargePrice != 0){
			if($chargeReason == ""){
				$chargeReasons = "가격변경이유: no";
			}else{
				$chargeReasons = "가격변경이유: ".$chargeReason;
			}
		}else{
			$chargeReasons = "";	
		}
        
        while ($camp_row = mysqli_fetch_assoc($camp_qry)) {
            $campName = $camp_row['campingName'];
        }
		
        $reservationListString .= '<div><div style="float: left;line-height:10px;">';
			// condition to support any mobile device (phones or tablets)
			if ( $detect->isMobile() ) {
				$reservationListString .= '<h5 style="">
					<span style="color:blue;">
						<span style="color:#525252;">
							<i class="fa fa-clock-o fa-fw"></i> ' .date('Y-m-d H:i', strtotime($booking_row['bookingDate'])) . 
						'</span>&nbsp;';
						/* start expiration for booking date and delay payment date */
						if($booking_row['pending']==0 and $booking_row['sms_auto_cancel']==0){
							$reservationListString .= '<span id="remove_add_expriration_date'.$bookingid.'" class="padding_10 priceTag" title="Expiration Date" style="width:auto; height:24px;padding:3px;background-color:#F00;margin-right:5px;">' . date('Y-m-d H:i:s',$cancel_delaypayment_date) . '</span>';
						}else if($booking_row['pending']==1 and $booking_row['sms_auto_cancel']==0){
							if($booking_row['delaypaymentdate'] != "0000-00-00 00:00:00"){
								$reservationListString .= '<span class="padding_10 priceTag" id="remove_add_expriration_date'.$bookingid.'" title="Expiration Date" style="width:auto; height:24px;padding:3px;background-color:#F00;margin-right:5px;">' . date('Y-m-d H:i:s',$cancel_delaypayment_date) . '</span>';
							}else if($booking_row['bookingDate'] != "0000-00-00 00:00:00"){
								$reservationListString .= '<span class="padding_10 priceTag" id="remove_add_expriration_date'.$bookingid.'" title="Expiration Date" style="width:auto; height:24px;padding:3px;background-color:#F00;margin-right:5px;">' . date('Y-m-d H:i:s',$cancel_booking_date) . '</span>';
							}
						}
						/* end expiration for booking date and delay payment date */
						$reservationListString .= '<p style="margin-top:10px;">'; 
						if(!isset($_SESSION['userid'])){  
							$reservationListString .= $campName . ' <i class="fa fa-angle-right fa-fw"></i><br />'  ;
						}
						$reservationListString .= $roomType .'</p>	  						
					</span>
				</h5>';
			}else{
				$reservationListString .= '<h5 style="">
					<span style="color:blue;">
						<span style="color:#525252;">
							<i class="fa fa-clock-o fa-fw"></i> ' . date('Y-m-d H:i', strtotime($booking_row['bookingDate'])) . 
						'</span> &nbsp; '; 
						if(!isset($_SESSION['userid'])){  
							$reservationListString .= $campName . ' <i class="fa fa-angle-right fa-fw"></i>'  ;
						}
						$reservationListString .=  $roomType . 
					'</span>
				</h5>';
				
				
			}
			$reservationListString .= '<div class="booking_info">';
			// first explode ] sign
			$exploadPaymentMethod = explode("]", $paymentMethod);
			$exploadPaymentMethod[0]; 
			// second explode [ sign from $exploadPaymentMethod
			$exploadPaymentMethod2 = explode("[", $exploadPaymentMethod[0]);
			$exploadPaymentMethod2[1]; 
			
			// Any mobile device (phones or tablets).
			if ( $detect->isMobile() ) {
				$reservationListString .= '<table class="table table-bordered table-hover" style="background-color:#f5f5f5;width:100%;font-size:13px !important;border:0px solid red;">
					<tr style="background-color:#EEEEEE;">
						<td style="width:30%;">체크인</td>
						<td style="width:30%;">체크아웃</td>
						<td style="width:40%;">고객명</td>
					</tr>
					<tr>
						<td style="width:30%;">'.date('Y-m-d', strtotime($booking_row['checkinDate'])).'</td>
						<td style="width:30%;">'.date('Y-m-d', strtotime($booking_row['checkoutDate'])).'</td>
						<td style="width:40%;">'.$userUsername.'</td>
					</tr>
					<tr style="background-color:#EEEEEE;">
						<td style="width:30%;">전화번호</td>
						<td style="width:30%;">전체인원</td>
						<td style="width:40%;">성인추가</td>
					</tr>
					<tr>
						<td style="width:30%;">'.$getPhoneNumber.'</td>
						<td style="width:30%;">' . $adult . ' 명</td>
						<td style="width:40%;">' . $extraAdult[0] . '명</td>
					</tr>
					<tr style="border:0px solid red;">
						<td style="width:30%;background-color:#EEEEEE;">어린이추가</td>
						<td style="width:30%;background-color:#EEEEEE;">결제수단</td>
						<td style="border:none;width:40%;"></td>
					</tr>
					<tr>
						<td style="width:30%;">' . $extraChildren[0] . '명</td>
						<td style="width:30%;">';
						
						if($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제"){
							$reservationListString .= $paymentMethod;	
						}else if ($exploadPaymentMethod2[1]=="다른 방법" || $exploadPaymentMethod2[1]=="기타결제"){
							$reservationListString .= $exploadPaymentMethod2[1];
						}else if (startsWith($paymentMethod, 'ezwel-') || endsWith($paymentMethod, '-ezwel') ){
							$reservationListString .= '이지웰';	
						}else{
							$reservationListString .= $paymentMethod;	
						}
						/*if($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제"){
							$reservationListString .= $paymentMethod;	
						}else if ($paymentMethod=="다른 방법" || $paymentMethod=="기타결제"){
							$reservationListString .= $exploadPaymentMethod2[1];
						}else{
							$reservationListString .= $booking_row['paymentmethod'];	
						}*/
						$reservationListString .= '</td>
						<td style="border:none;width:40%;"></td> 
					</tr>
					
				</table>';
			}else{
				$reservationListString .= '
					<div id="sub_tabe_reservationHistory'.$bookingid.'">
						<table class="table table-bordered table-hover" style="background-color:#f5f5f5;font-size:18px;">
							<thead>
								<tr style="text-align:center;">
									<td>체크인</td>
									<td>체크아웃</td>
									<td>고객명</td>
									<td>전화번호</td>
									<td>전체인원</td>
									<td>성인추가</td>
									<td>어린이추가</td>
									<td>결제수단</td>
								</tr>
							</thead>
							<tbody>
								<tr style="text-align:center;">
									<td>'.date('Y-m-d', strtotime($booking_row['checkinDate'])).'</td>
									<td>'.date('Y-m-d', strtotime($booking_row['checkoutDate'])).'</td>
									<td>'.$userUsername.'</td>
									<td>'.$getPhoneNumber.'</td>
									<td>' . ($adult == 0 ? '2' : $adult)  . ' 명</td>
									<td>' . $extraAdult[0] . '명</td>
									<td>' . $extraChildren[0] . '명</td>
									<td>';
									
									if($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제"){
										$reservationListString .= $paymentMethod;	
										if($booking_row['reservation_by']==3) {
											$reservationListString .= '(B)';
										}
									}else if ($exploadPaymentMethod2[1]=="다른 방법" || $exploadPaymentMethod2[1]=="기타결제"){
										$reservationListString .= $exploadPaymentMethod2[1];
									}else if (startsWith($paymentMethod, 'ezwel-') || endsWith($paymentMethod, '-ezwel') ){
										$reservationListString .= '이지웰';	
									}else if($paymentMethod=="포인트") {
										$reservationListString .= $paymentMethod .'(B)';
									}else{
										$reservationListString .= $paymentMethod;	
									}
									/*if($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제"){
										$reservationListString .= $paymentMethod;	
									}else if(!($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제")){
										$reservationListString .= "기타결제";
									}else{
										$reservationListString .= $exploadPaymentMethod2[1];
									}*/
									$reservationListString .= '</td>
								</tr>
							</tbody>
						</table>
					</div>
					';
			}
				
		$reservationListString .='</div></div>';
		
		// start all button at right in table
		if ( $detect->isMobile() ) {
			$styleCheckBox = "padding:0px;";
			$reservationListString .='<div style="float:left;">';
		}else{
			$styleCheckBox = "";
			$reservationListString .='<div style="float:right;">';	
		}    
		$reservationListString .= '<!-- total price after discount -->'.($promotion_discount == 0 ?'':'<span class="padding_10 priceTag" id="totalprice_style_'.$bookingid.'" style="width:auto; height:36px;padding:6px;background-color:#00CCFF; cursor:pointer" onClick="discount_price('.$promotion_explode[0].','.$promotion_explode[1].','.$promotion_explode[2].','.$promotion_explode[3].','.$totalPrice.')" title="할인 가격"><u>'. $defaultCurrency . number_format($promotion_explode[3]).'</u></span> '). 
		
			'<!-- total price button -->'.
			($chargePrice != 0 ? '<span class="padding_10 priceTag" id="totalprice_style_'.$bookingid.'" style="width:auto; height:36px;padding:6px;background-color:#c4c4c4;">' . $defaultCurrency . number_format($totalPrice) . '</span>' : '<span class="padding_10 priceTag" id="totalprice_style_'.$bookingid.'" style="width:auto; height:36px;padding:6px;background-color:#0079c9;">' . $defaultCurrency . number_format($totalPrice) . '</span>').'
			<!-- charge price button -->
			'.($chargePrice != 0 ? '<span id="show_charge_price_'.$bookingid.'" class="padding_10 priceTag" style="width:auto; height:36px;padding:6px;background-color:#0079c9;margin-right:5px;">' . $defaultCurrency. " " . number_format($chargePrice) . '</span>' : '<span id="show_charge_price_'.$bookingid.'" style=""></span>');
			
			if(!$detect->isMobile()){
				/* start expiration for booking date and delay payment date */
				if($booking_row['pending']==0 and $booking_row['sms_auto_cancel']==0){
					$reservationListString .= '<span id="remove_add_expriration_date'.$bookingid.'" class="padding_10 priceTag" title="Expiration Date" style="width:auto; height:36px;padding:6px;background-color:#F00;margin-right:5px;">' . date('Y-m-d H:i:s',$cancel_delaypayment_date) . '</span>';
				}else if($booking_row['pending']==1 and $booking_row['sms_auto_cancel']==0){
					if($booking_row['delaypaymentdate'] != "0000-00-00 00:00:00"){
						$reservationListString .= '<span class="padding_10 priceTag" id="remove_add_expriration_date'.$bookingid.'" title="Expiration Date" style="width:auto; height:36px;padding:6px;background-color:#F00;margin-right:5px;">' . date('Y-m-d H:i:s',$cancel_delaypayment_date) . '</span>';
					}else if($booking_row['bookingDate'] != "0000-00-00 00:00:00"){
						$reservationListString .= '<span class="padding_10 priceTag" id="remove_add_expriration_date'.$bookingid.'" title="Expiration Date" style="width:auto; height:36px;padding:6px;background-color:#F00;margin-right:5px;">' . date('Y-m-d H:i:s',$cancel_booking_date) . '</span>';
					}
				}
				/* end expiration for booking date and delay payment date */
			}
			
			/* start btn status (pending) */
			// pending condition on title, style, condition on icon and event
			
			if($booking_row['pending']==3){
				$pendingTitle = '결제취소';
				$pendingStyle = 'background:#C4C4C4;';
				$pendingIcon = '<i class="fa fa-minus-circle"></i>';
				$pendingClick = 'onClick="updateCancelReservationHistory(' . $bookingid . ')"';
			}else if($booking_row['pending']==0){
				$pendingTitle = '결제연장';
				$pendingStyle = 'background:rgb(255, 0, 0);';
				$pendingIcon= '<i class="fa fa-exclamation-circle"></i>';
				$pendingClick = 'onClick="updatePendingReservationHistory(' . $bookingid . ')"';
			}else if($booking_row['pending']==1){
				$pendingClass = 'paymentNotYetPaidReservationHistory';
				$pendingTitle = '결제미납';
				$pendingStyle = 'background:#FFC000;';
				$pendingIcon = '<i  class="fa fa-question-circle"></i>';
				$pendingClick = 'onClick="updatePaymentNotYetPaidReservationHistory(' . $bookingid . ')"';
			}else if($booking_row['pending'] == 2){ 
			
				$pendingTitle ='결제완료';
				$pendingStyle = 'background:green';
				if ( $booking_row['reservation_by'] == 2 ){
					$result_ezwel = exec_query_utf8("SELECT status FROM tbl_ezwel WHERE active = 1 AND booking_id =". $booking_row['id']);
					$row_ezwel = mysqli_fetch_object ( $result_ezwel );
					if ( $row_ezwel->status == 'hold' ){
						$pendingTitle = '결제연장';
						$pendingStyle = 'background:red';
					}
				}
				
				$pendingIcon = '<i  class="fa fa-check-circle"></i>';
				$pendingClick = 'onClick="updatePaymentPaidReservationHistory(' . $bookingid . ')"';
			}
					
			$reservationListString .= '<span class="padding_10 moreBookingInfo_btn '.$pendingClass.'" title="'.$pendingTitle.'" id="pendingReservationHistory'. $bookingid . '" '.(isset($_SESSION['adminid'])?$pendingClick:"").' class="padding_10" style=" color:#fff;'.$pendingStyle.'">'.$pendingIcon.'</span>
			<!-- end btn status (pending) -->
			
			<span title="정보더보기" class="padding_10 moreBookingInfo_btn" onClick="moreBookingInfo(\'moreInfo_' . $bookingid . '\')"><i class="fa fa-list-ul"></i></span>';
			if($detect->isMobile()){
				$reservationListString .= '<span style="margin-left:5px;" class="padding_10 moreBookingInfo_btn openUserInfoDialog" onclick="editReservationHistory(' . $bookingid . ');"><i class="fa fa-pencil-square-o"></i> Edit</span>';
			}else{
			$reservationListString .= '<span style="margin-left:5px;" class="padding_10 moreBookingInfo_btn openUserInfoDialog" title="정보수정" data-toggle="modal" data-target="#myModals'.$bookingid.'"><i class="fa fa-pencil-square-o"></i></span>';
			}
			
			if (isset($_SESSION['adminid'])){
				$reservationListString .= '<span class="padding_10 moreBookingInfo_btn" title="삭제" style="margin-left:4px;" onClick="deleteReservationHistory(' . $bookingid . ')"><i class="fa fa-trash-o"></i></span>
				<span class="padding_10 moreBookingInfo_btn" style="background:none;'.$styleCheckBox.'">
					<input type="checkbox" value="'.$bookingid.'" class="booking-delete-multi" id="data">
				</span>';
			}else if (isset($_SESSION['userid'])){
				if($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제" || startsWith($paymentMethod, 'ezwel-') || endsWith($paymentMethod, '-ezwel')){
					
				}else{
					$reservationListString .= '<span class="padding_10 moreBookingInfo_btn" title="삭제" style="margin-left:4px;" onClick="deleteReservationHistory(' . $bookingid . ')"><i class="fa fa-trash-o"></i></span>';
				}
			}else{
				
			}
			
			$reservationListString .= '</div>  
			
		';
		
        $summaryData = '<table class="table table-striped table-bordered table-hover"><thead><tr><td class="tableCellCenter">번호</td><td>예약일</td><td>수량</td><td>가격</td><td>총액</td></tr></thead><tbody><tr>';
        $summaryTotalPrice = 0;
        $summaryid = 0;

        //show extra person			
        $str_extraAdult = ' x' . $extraAdult[0] . ' : <span style="color:#b77700;">'  . $defaultCurrency . number_format($extraAdult[1]) . '</span>';
        if ($extraAdult[0] > 0) {
            $summaryid ++;
            $summaryData .= '<tr><td class="tableCellCenter">' . $summaryid . '</td><td><!--Extra Person-->추가인원</td><td>' . $extraAdult[0] . '</td><td>' . $defaultCurrency . number_format($extraAdult[1] / $extraAdult[0]) . '</td><td>' . $defaultCurrency . number_format($extraAdult[1]) . '</td></tr>';
            $summaryTotalPrice += $extraAdult[1];
        }

        //show extra children			
        $str_extraChildren = ' x' . $extraChildren[0] . ' : <span style="color:#b77700;">' . $defaultCurrency . number_format($extraChildren[1]) .  '</span>';
        if ($extraChildren[0] > 0) {
            $summaryid ++;
            $summaryData .= '<tr><td class="tableCellCenter">' . $summaryid . '</td><td>Extra Children</td><td>' . $extraChildren[0] . '</td><td>'. $defaultCurrency . number_format($extraChildren[1] / $extraChildren[0])  . '</td><td>'  . $defaultCurrency . number_format($extraChildren[1]). '</td></tr>';
            $summaryTotalPrice += $extraChildren[1];
        }

        //display by day
        $eachDateItem = extraItemDecode($booking_row['extraItem'], 2);
        $begin = new DateTime($chechinDate);
        $end = new DateTime($checkoutDate);
        //$end = $end->modify( '+1 day' ); 
        $period = new DatePeriod($begin, new DateInterval('P1D'), $end);

        $str_dateRange = '<div>';
        $totalNight = 0;
        foreach ($period as $key => $date) {
            $num_night = $key + 1;

            $itemList = '';
            foreach ($eachDateItem as $item) {
                if ($item['night'] == $num_night) {
                    $itemByforDate = $item['item'];
                    foreach ($itemByforDate as $row) {
                        $itemList .= $row['itemName'] . ' x ' . $row['quantity'] . '<br />';
                    }
                }
            }

            $str_dateRange.= '<div style="display:inline-block; padding:5px; border:1px solid #b9b9b9; margin:5px;  vertical-align:top;">
				<div style="border-bottom:1px solid #999;">' . $date->format("m/d/Y") . ' : <span style="color:#b77700;">' . $defaultCurrency . number_format($nightPrice[$key]) . '</span> </div>
				<div>' . (($itemList == '') ? '<span style="color:red;">옵션추가없음</span>' : $itemList) . '</div>
				</div>';

            $summaryid ++;
            $summaryData .= '<tr><td class="tableCellCenter">' . $summaryid . '</td><td>' . $date->format("m/d/Y") . '</td><td>1</td><td>' . $defaultCurrency  . number_format($nightPrice[$key]). '</td><td>' . $defaultCurrency . number_format($nightPrice[$key]) . '</td></tr>';
            $summaryTotalPrice += $nightPrice[$key];

            $totalNight++;
        }
        $str_dateRange.='</div>';

        //display by item
        $str_item = '<div>';
        for ($i = 0; $i < count($eachItem); $i++) {
            $quantityBydate = $eachItem[$i]['quantity'];
            usort($quantityBydate, reservationlist_callback($a, $b)); //sort by night number
            $byDateList = '<div>';
            /*foreach ($quantityBydate as $row) {
                $nightNumber = $row['night'];
                $byDateList .= $nightNumber . '<sup>' . ($nightNumber == 1 ? "st" : ($nightNumber == 2 ? "nd" : ($nightNumber == 3 ? "rd" : "th"))) . '</sup> night x ' . $row['quantity'] . '<br />';
           
				$eachItemTotalQuantity = array_sum(array_map(reservationlist_callback2($var), $quantityBydate));
				$byDateList .= '</div>';
				$str_item.='<div style="display:inline-block; vertical-align:top; border:1px solid #b9b9b9; padding:5px; margin:3px;">
										<div style="border-bottom:1px solid #999;">' . $eachItem[$i]['name'] . ' x ' . $row['quantity'] . ' : <span style="color:#b77700;">'. $defaultCurrency  . number_format($getItemPrice[$eachItem[$i]['id']]) . '</span></div></div>';
			}

            $summaryid ++;
            $summaryData .= '<tr><td class="tableCellCenter">' . $summaryid . '</td><td>' . $eachItem[$i]['name'] . '</td><td>' . $eachItemTotalQuantity . '</td><td>' . $defaultCurrency  . number_format($getItemPrice[$eachItem[$i]['id']] / $eachItemTotalQuantity). '</td><td>' . $defaultCurrency  . number_format($getItemPrice[$eachItem[$i]['id']]). '</td></tr>';*/
			$summaryid ++;
			foreach ($quantityBydate as $row) {
                $nightNumber = $row['night'];
                $byDateList .= $nightNumber . '<sup>' . ($nightNumber == 1 ? "st" : ($nightNumber == 2 ? "nd" : ($nightNumber == 3 ? "rd" : "th"))) . '</sup> night x ' . $row['quantity'] . '<br />';
           
				$eachItemTotalQuantity = array_sum(array_map(reservationlist_callback2($var), $quantityBydate));
				$byDateList .= '</div>';
				$str_item.='<div style="display:inline-block; vertical-align:top; border:1px solid #b9b9b9; padding:5px; margin:3px;">
										<div style="border-bottom:1px solid #999;">' . $eachItem[$i]['name'] . ' x ' . $row['quantity'] . ' : <span style="color:#b77700;">'. $defaultCurrency  . number_format($getItemPrice[$eachItem[$i]['id']]) . '</span></div></div>';
				
				$summaryData .= '<tr><td class="tableCellCenter">' . $summaryid . '</td><td>' . $eachItem[$i]['name'] . '</td><td>' . $row['quantity'] . '</td><td>' . $defaultCurrency  . number_format($getItemPrice[$eachItem[$i]['id']] / $row['quantity']). '</td><td>' . $defaultCurrency  . number_format($getItemPrice[$eachItem[$i]['id']]). '</td></tr>'; 
			}
			
            $summaryTotalPrice += $getItemPrice[$eachItem[$i]['id']];
        }
        $str_item.=((count($eachItem) == 0) ? '<span style="color:red;">옵션추가없음</span>' : '') . '</div>';
        $summaryData .= '</tbody>
							<tfoot>
							<tr style="background:#e7f7dd; font-weight:bold; color:blue;"><td colspan="4">총금액</td><td>' . $defaultCurrency . number_format($summaryTotalPrice) . '</td></tr> 
							</tfoot></table>';
        $reservationListString .= '<div style="clear:both;"></div><div class="moreInfo" style="display:none; padding-top:10px;" id="moreInfo_' . $bookingid . '">
                                    <ul class="nav nav-tabs">
										<li class="active"><a href="#infoOfuser' . $bookingid . '" data-toggle="tab"><!--User Information-->고객메세지</a></li>
                                        <li><a href="#itemDetail' . $bookingid . '" data-toggle="tab"><!--Item &amp; Service-->옵션추가 (' . count($eachItem) . ')</a></li>
                                      <!-- <li><a href="#dateRange' . $bookingid . '" data-toggle="tab">Date Range (' . $totalNight . ')</a></li>  -->
										<li><a href="#summary' . $bookingid . '" data-toggle="tab"><!--Summary-->날짜별 요약 (' . $totalNight . ')</a></li>
                                        <li><a href="#info' . $bookingid . '" data-toggle="tab"><!--Information-->예약정보</a></li>
                                       

                                    </ul>
                                    
                                    <div class="tab-content">
                                        <div class="tab-pane padding_10 fade" id="info' . $bookingid . '">    
											<p>예약자이름: ' . $userUsername . '</p>
											<p>전화번호: ' . $PhoneNumberUser . '</p>
											<p>이메일: ' . $EmailUser . '</p>
											<p>예약기간: ' . $chechinDate . ' to ' . $checkoutDate . '</p>
                                            <p>총인원: ' . $adult . ' 명 </p>
                                            <p>성인추가: ' . $str_extraAdult . '명</p>
                                            <!--<p>Children: ' . $children . '</p>-->
                                            <p>어린이추가: ' . $str_extraChildren . '명</p>	';
											if($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제"){
												$reservationListString .= '<p>결제수단: '.$paymentMethod.' </p>';	
											}
											if($detect->isMobile()){
												$stySummaryData = "padding:0px;";
											}else{
												$stySummaryData = "";	
											}
                                        $reservationListString .= '</div>
                                        <div class="tab-pane padding_10 fade" id="itemDetail' . $bookingid . '"> 
                                            ' . $str_item . '
                                        </div>
                                        <!-- <div class="tab-pane padding_10 fade" id="dateRange' . $bookingid . '"> 
                                            ' . $str_dateRange . '
                                        </div> -->
										<div class="tab-pane padding_10 fade" id="summary' . $bookingid . '"> 
                                            <div class="table-responsive col-lg-6" style="'.$stySummaryData.'">
                                            ' . $summaryData . ' 
                                            </div>
                                        </div>
                                        <div class="tab-pane padding_10 fade in active" id="infoOfuser' . $bookingid . '">       
                                            <div id="passingUserinfo'.$bookingid.'" style="float:left">';
												/*if(!($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제")){
													$exploadPaymentMethod = explode("]", $paymentMethod);
													$exploadPaymentMethod[1]; // exploadPaymentMethod 1
													$reservationListString .= '<p>결제수단: '.$exploadPaymentMethod[1].' </p>';	
												}*/
												
												if($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제"){
													$reservationListString .= $paymentMethod;	
												}else if ($exploadPaymentMethod2[1]=="다른 방법" || $exploadPaymentMethod2[1]=="기타결제"){
													$reservationListString .= '결제수단:'.$exploadPaymentMethod2[1];
												}else if (startsWith($paymentMethod, 'ezwel-') || endsWith($paymentMethod, '-ezwel') ){
													$reservationListString .= 'ezwel';	
												}else{
													$reservationListString .= $paymentMethod;	
												}
												
                                                $reservationListString .= '<p>'.$chargeReasons.' </p>
                                            </div>
                                           <div style="float:right">
                                           <!-- <p style="text-align:right;"><span data-toggle="modal" data-target="#myModal.$bookingid." class="openUserInfoDialog"><label class="padding_10 moreBookingInfo_btn"><i class="fa fa-pencil-square-o"></i></label></span></p>-->	
                                           
                                            </div>
                                    </div>
                                </div> 
								
					 	</div>
					 </div>
						  
					 <!-- start modal editing Reservation History -->
					  <div class="modal fade" id="myModals'.$bookingid.'" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
						<div class="modal-dialog">
						  <div class="modal-content">
							<div class="modal-header">
							  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
							  <h4 class="modal-title" id="myModalLabel">고객정보</h4>
							</div>
							<div class="modal-body">
								<form role="form" id="userInformations">	
									
								<div class="form-group">
									<label>고객명 : </label>
									 <input type="text" name="txtUsername" value="' . $userUsername . '" id="txtUsername'.$bookingid.'" class="form-control" placeholder="Username" required>
								</div> 
								 <div class="form-group">
									<label>전화번호 : </label>
	
									<input type="text" name="txtPhoneNumber" value="' . $PhoneNumberUser . '" id="txtPhoneNumber'.$bookingid.'" class="form-control" placeholder="Phone Number" required>
								</div>                                     
								<div class="form-group">
									<label>이메일</label>
									<input type="text" name="txtEmail" value="' . $EmailUser .'" id="txtEmail'.$bookingid.'" class="form-control" placeholder="Email">
								</div>'; //company_sessionid $_SESSION['userid']
								if(isset($_SESSION['adminid'])){
									$cate_qrys = exec_query_utf8("SELECT code FROM tblportaladmin admin INNER JOIN tblcategory cate ON admin.levelid = cate.id WHERE admin.id='".$_SESSION['adminid']."'");							
								}else{
									$cate_qrys = exec_query_utf8("SELECT code FROM tblportaladmin admin INNER JOIN tblcategory cate ON admin.levelid = cate.id WHERE admin.id='".$company_sessionid."'");							
								}
								$admin_level = '' ;
								while($cate_rows = mysqli_fetch_assoc($cate_qrys)){
									$admin_level = $cate_rows['code'] ;
								}
								
								//if ( $admin_level == 'Level 1'){
									$reservationListString .='<div class="form-group">
										<label> 변경가격</label>
										<input type="hidden" name="txtCheckinDate" value="' . $checkinDate . '" id="txtCheckinDate'.$bookingid.'" class="form-control">
										<input type="hidden" name="txtCheckoutDate" value="' . $checkoutDate . '" id="txtCheckoutDate'.$bookingid.'" class="form-control">
										<input type="text" name="txtChargePrice" value="' . $chargePrice . '" id="txtChargePrice'.$bookingid.'" class="form-control" placeholder="">
									</div>
									<div class="form-group">
										<label>가격변경이유</label>
										<textarea name="txtChargeReason" id="txtChargeReason'.$bookingid.'" class="form-control">'. $chargeReason . '</textarea>
									</div>';
								//}
	
								$reservationListString .=' <div class="form-group">
									<label>결제수단</label>
									<input type="text" readOnly name="txtPaymentMethod" value="' . $paymentMethod . '" id="txtPaymentMethod" class="form-control" placeholder="Payment Method">
								</div>
								<div class="form-group">
									<button type="submit"  data-dismiss="modal" onClick="EditUserInfoReservationHistory(' . $bookingid . '); return false;" class="btn btn-primary"><i class="fa fa-plus-circle fa-fw"></i> 저장하기</button>
								</div>
							</form>  
							</div>
						</div>               
					</div>
				</div>
				<!-- end modal editing Reservation History -->
			</td>
		</tr>';

        $i++;
    }
    //No reservation Found
    if ($reservationListString == '') {
        $reservationListString = '<tr><td colspan="2" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> 해당결과가 존재하지 않습니다
</td></tr>';
    }
    $data = array('statusAction'=>$selectStatus,/*'sms1'=> $admin_cancel_sms_1, ' sms2' => $admin_cancel_sms_2,*/ 'list' => $reservationListString, 'targetPage' => $targetPage, 'totalRow' => $totalRow, 'totalPages' => $totalPages, 'gotoSelectNum' => $gotoSelectNum, 'nav_btn_disable' => $nav_btn_disable);

    echo json_encode($data);
	
}else if($cmd == 'discount_price_info'){
	$promotion_id = post('promotion_id');
	$discount_type = post('discount_type');
	$discount = post('discount');
	$total = post('total');
	$beforeDisTotal = post('beforeDisTotal');
	
	$discount_info='';
	$promotionCode_qry = exec_query_utf8("SELECT * FROM tblpromotioncode WHERE id = '$promotion_id' ");
	while($data_promotion = mysqli_fetch_object($promotionCode_qry)){
		if($discount_type == 1){
			$discount_info .= '<!--name of promotion code-->프로모션 코드명: '.$data_promotion->promo_code.' 
			<br> <!--Total Discount--> 할인: '. $defaultCurrency . number_format($beforeDisTotal - $total).' 
			<br> <!--Total-->할인적용총금액: '. $defaultCurrency . number_format($total);	
		}else{
			$discount_info .= '<!--name of promotion code-->프로모션 코드명: '.$data_promotion->promo_code.'
			<br>할인: '. $defaultCurrency . number_format($discount).' 
			<br> 할인적용총금액: '. $defaultCurrency . number_format($total);
		} 
	} 
	echo $discount_info;
}elseif ($cmd == 'scheduleList') {
   //$keyword = post("keyword");
	if(isset($_SESSION['adminid'])){$companyid = post("companyid");}else{$companyid = $company_sessionid;}	
	$campingType = post('campingType');
	$activeStatus = post('activeStatus');	
	$currentPage = intval(post("currentPage"));
	$rowsPerPage = post("rowsPerPage");
	$navAction = post("navAction");
	
	//if($companyid==0 or $companyid==''){$companyid=$company_sessionid;}
	
	$sql_condition = 'WHERE';
	if($companyid>0){$sql_condition.=($sql_condition=='WHERE'?'':' AND')." userid = $companyid";}
	//if($keyword<>''){$sql_condition.=($sql_condition=='WHERE'?'':' AND')." id LIKE '%$keyword%'";}
	/*if($campingType<>'all'){$sql_condition.=($sql_condition=='WHERE'?'':' AND')." roomNumberid IN (SELECT id FROM tblcampingeachroom WHERE roomid IN (SELECT id FROM tblcampingroom WHERE campingid IN (SELECT id FROM tblcamping WHERE campingCategory = $campingType)))";}*/
	if($activeStatus<>'all'){$sql_condition.=($sql_condition=='WHERE'?'':' AND')." active=$activeStatus";}
	if($sql_condition=='WHERE'){$sql_condition = '';}
	
	//work with total page
	$navRow_qry = exec_query_utf8("SELECT * FROM tblschedule $sql_condition ORDER BY datetime DESC");
	$totalRow = mysqli_num_rows($navRow_qry);
	$totalPages = $totalRow/$rowsPerPage;
	if($totalRow%$rowsPerPage>0){$totalPages = intval($totalPages) + 1;}
	
	//get the target page number	
	$targetPage = 1;$nav_btn_disable = array();
	if($navAction=='first'){
		$targetPage = 1;
	}elseif($navAction=='prev'){
		$targetPage = $currentPage-1;
	}elseif($navAction=='next'){
		$targetPage = $currentPage+1;
	}elseif($navAction=='last'){
		$targetPage = $totalPages;
	}elseif($navAction=='goto'){
		$targetPage = $currentPage;
	}
	//get goto select list
	$gotoSelectNum = array();
	for($i=1;$i<=$totalPages;$i++){
		$gotoSelectNum[] = $i;
	}
	
	if($totalPages==1 or $totalPages==0){
		$nav_btn_disable = array('nav_first'=>0,'nav_prev'=>0,'nav_next'=>0,'nav_last'=>0);
	}elseif($targetPage==1){
		$nav_btn_disable = array('nav_first'=>0,'nav_prev'=>0,'nav_next'=>1,'nav_last'=>1);
	}elseif($targetPage==$totalPages){
		$nav_btn_disable = array('nav_first'=>1,'nav_prev'=>1,'nav_next'=>0,'nav_last'=>0);
	}else{
		$nav_btn_disable = array('nav_first'=>1,'nav_prev'=>1,'nav_next'=>1,'nav_last'=>1);
	}

	$startIndex = ($targetPage-1)*$rowsPerPage;
	$scheduleListString = '';$i=$startIndex+1;
	$schedule_qry = exec_query_utf8("SELECT * FROM tblschedule $sql_condition ORDER BY datetime DESC LIMIT ".$startIndex.",$rowsPerPage");
	while($schedule_row = mysqli_fetch_assoc($schedule_qry)){	
		$rowid = $schedule_row['rowid'];
		$type = $schedule_row['type'];
		$siteName = 'N/A';
		
		if($type==1){
			$campingName = singleCell_qry("campingName","tblcamping","id=$rowid LIMIT 1");
			$siteName = $campingName;
		}elseif($type==2){
			$campingName = singleCell_qry("campingName","tblcamping","id=(SELECT campingid FROM tblcampingroom WHERE id=$rowid LIMIT 1) LIMIT 1");
			$campingRoomType = singleCell_qry("roomName","tblcampingroom","id=$rowid LIMIT 1");
			$siteName = $campingName.' <i class="fa fa-angle-right fa-fw"></i> '.$campingRoomType;
		}elseif($type==3){
			$campingName = singleCell_qry("campingName","tblcamping","id=(SELECT campingid FROM tblcampingroom WHERE id=(SELECT roomid FROM tblcampingeachroom WHERE id=$rowid LIMIT 1) LIMIT 1) LIMIT 1");
			$campingRoomType = singleCell_qry("roomName","tblcampingroom","id=(SELECT roomid FROM tblcampingeachroom WHERE id=$rowid LIMIT 1) LIMIT 1");
			$campingRoomNumber = singleCell_qry("roomNumber","tblcampingeachroom","id=$rowid LIMIT 1");
			$useCustomName = singleCell_qry("useCustomName","tblcampingeachroom","id=$rowid LIMIT 1");
			$customName = singleCell_qry("customName","tblcampingeachroom","id=$rowid LIMIT 1");
			$siteName = $campingName.' <i class="fa fa-angle-right fa-fw"></i> '.$campingRoomType.' <i class="fa fa-angle-right fa-fw"></i> '.($useCustomName==1?$customName:'Room '.$campingRoomNumber);
		}
		
		//display date period
		if(!is_null($schedule_row['fromDate']) and !is_null($schedule_row['toDate'])){
			$datePeriod = '<span onClick="popupMsg(\'popupMsg\',\'Note\',\''.$schedule_row['note'].'\')">'.date('d-M-Y',strtotime($schedule_row['fromDate'])).' to '.date('d-M-Y',strtotime($schedule_row['toDate'])).'</span>';
		}else{$datePeriod = '<span onClick="popupMsg(\'popupMsg\',\'Note\',\''.$schedule_row['note'].'\')">Permanent</span>';}
		
		//display selected day
		$selectedDay = '<span onClick="popupMsg(\'popupMsg\',\'Applied Day\',\'This schedule is applied to all days of week.\')">All Days</span>';
		$getDay = trim($schedule_row['day']);
		if($getDay<>''){if(count(explode(',',$getDay))<7){$selectedDay = '<span onClick="popupMsg(\'popupMsg\',\'Applied Day\',\''.strtoupper($getDay).'\')">Custome</span>';}}
			
		$scheduleListString .= '<tr>
									<td class="tableCellCenter">'.$i.'</td>
									<td>
										<h5>'.$siteName.'</h5>
										<div class="sub_des">
											<div class="schedule_period"><i class="fa fa-calendar fa-fw"></i> '.$datePeriod.'</div>
											<div class="schedule_days"><i class="fa fa-caret-right fa-fw"></i> '.$selectedDay.'</div>
											<div><i class="fa fa-clock-o fa-fw"></i>'.date('d-M-Y H:i',strtotime($schedule_row['datetime'])).'</div>
										</div>
									</td>
									<td class="tableCellCenter action_td_verticle">
										<div><button type="submit" class="btn btn-primary btn-xs" onclick="iniModifySchedule('.$schedule_row['id'].')"><i class="fa fa-pencil-square-o fa-fw"></i> ìˆ˜ì •</button></div>
										<div><button style="'.($schedule_row['active']==0?'background:red;':'').'" type="submit" class="btn btn-primary btn-xs" onclick="iniEnableSchecule('.$schedule_row['id'].','.$schedule_row['active'].')"><i class="fa fa-times-circle-o fa-fw"></i> '.($schedule_row['active']==0?'활성화':'비활성화').'</button></div>
									</td>
								</tr>';	
			
			
		$i++;
	}
	if($scheduleListString == ''){$scheduleListString = '<tr><td colspan="3" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> No Schedule Found</td></tr>';}
	$data = array('list'=>$scheduleListString,'targetPage'=>$targetPage,'totalRow'=>$totalRow,'totalPages'=>$totalPages,'gotoSelectNum'=>$gotoSelectNum,'nav_btn_disable'=>$nav_btn_disable);
	
	echo json_encode($data);
	
} elseif ($cmd == 'roomNumberList') {

    $roomid = post('roomid');

    $roomList = '<option value="0">--- 선?? ---</option>';
    $eachRoom_qry = exec_query_utf8("SELECT * FROM tblcampingeachroom WHERE roomid=$roomid AND active=1");
    while ($eachRoom_row = mysqli_fetch_assoc($eachRoom_qry)) {
        $eachRoomid = $eachRoom_row['id'];
        $eachRoomName = ucfirst($eachRoom_row['useCustomName'] == 1 ? $eachRoom_row['customName'] : singleCell_qry("roomName", "tblcampingroom", "id=" . $eachRoom_row['roomid']) . ' ' . $eachRoom_row['roomNumber']);

        $roomList .= '<option value="' . $eachRoomid . '">' . $eachRoomName . '</option> ';
    }

    echo $roomList;
} elseif ($cmd == 'myRoomNumberList') {
    $eachroomid = post('roomid');
    $myresult = exec_query_utf8("SELECT tblcampingroom.id FROM tblcampingroom JOIN tblcampingeachroom ON tblcampingroom.id = tblcampingeachroom.roomid WHERE tblcampingeachroom.id=$eachroomid and tblcampingroom.active=1");
    while ($Room_row = mysqli_fetch_assoc($myresult)) {
        $camroomid = $Room_row['id'];
    }

    $roomList = '<option value="0">--- 선?? ---</option>';
    $eachRoom_qry = exec_query_utf8("SELECT * FROM tblcampingeachroom WHERE roomid=$camroomid AND active=1");
    while ($eachRoom_row = mysqli_fetch_assoc($eachRoom_qry)) {
        $eachRoomid = $eachRoom_row['id'];
        $eachRoomName = ucfirst($eachRoom_row['useCustomName'] == 1 ? $eachRoom_row['customName'] : singleCell_qry("roomName", "tblcampingroom", "id=" . $eachRoom_row['roomid']) . ' ' . $eachRoom_row['roomNumber']);

        $roomList .= '<option value="' . $eachRoomid . '" ' . ($eachRoom_row['id'] == $eachroomid ? 'selected' : '') . '>' . $eachRoomName . '</option> ';
        // $roomList .= '<option value="'.$eachRoomid.'" '.($eachRoom_row['id']==$eachroomselected?'selected':'').'>'.$eachRoom_row['id'].' = '.$eachroomselected.'</option> ';
    }
    echo $roomList;
} elseif ($cmd == 'MyroomList') { // add new start  for Room show dropdown
    $eachroomid = post('roomid');
    $myresult = exec_query_utf8("SELECT tblcampingroom.id FROM tblcampingroom JOIN tblcampingeachroom ON tblcampingroom.id = tblcampingeachroom.roomid WHERE tblcampingeachroom.id=$eachroomid and tblcampingroom.active=1");
    while ($Room_row = mysqli_fetch_assoc($myresult)) {
        $camroomid = $Room_row['id'];
    }

    $resultcomping = exec_query_utf8("SELECT tblcamping.id FROM tblcamping JOIN tblcampingroom ON tblcamping.id = tblcampingroom.campingid WHERE tblcampingroom.id = $camroomid");

    while ($Comping_row = mysqli_fetch_assoc($resultcomping)) {
        $mycompingid = $Comping_row['id'];
    }

    $roomList = '<option value="0">--- 선?? ---</option>';
    $Room_qry = exec_query_utf8("SELECT * FROM tblcampingroom WHERE campingid=$mycompingid AND active=1");


    while ($Room_row = mysqli_fetch_assoc($Room_qry)) {
        $Roomid = $Room_row['id'];
        //$eachRoomName = ucfirst($eachRoom_row['useCustomName']==1?$eachRoom_row['customName']:singleCell_qry("roomName","tblcampingroom","id=".$eachRoom_row['roomid']).' '.$eachRoom_row['roomNumber']);

        $roomList .= '<option value="' . $Roomid . '" ' . ($Room_row['id'] == $camroomid ? 'selected' : '') . '>' . $Room_row['roomName'] . '</option> ';
        // $roomList .= '<option value="'.$eachRoomid.'" '.($eachRoom_row['id']==$eachroomselected?'selected':'').'>'.$eachRoom_row['id'].' = '.$eachroomselected.'</option> ';
    }
    echo $roomList;
    // add new end
} elseif ($cmd == 'MyCompinglist') {
    // start add new  For comping show dropdown
    $eachroomid = post('roomid');
    $myresult = exec_query_utf8("SELECT tblcampingroom.id FROM tblcampingroom JOIN tblcampingeachroom ON tblcampingroom.id = tblcampingeachroom.roomid WHERE tblcampingeachroom.id=$eachroomid and tblcampingroom.active=1");
    while ($Room_row = mysqli_fetch_assoc($myresult)) {
        $camroomid = $Room_row['id'];
    }

    $resultcomping = exec_query_utf8("SELECT tblcamping.id FROM tblcamping JOIN tblcampingroom ON tblcamping.id = tblcampingroom.campingid WHERE tblcampingroom.id = $camroomid");

    while ($Comping_row = mysqli_fetch_assoc($resultcomping)) {
        $mycompingid = $Comping_row['id'];
    }



    $roomList = '<option value="0">--- 선?? ---</option>';
    $Comping_qry = exec_query_utf8("SELECT * FROM tblcamping WHERE active = 1");


    while ($Camping_row = mysqli_fetch_assoc($Comping_qry)) {
        $Camping_id = $Camping_row['id'];
        //$eachRoomName = ucfirst($eachRoom_row['useCustomName']==1?$eachRoom_row['customName']:singleCell_qry("roomName","tblcampingroom","id=".$eachRoom_row['roomid']).' '.$eachRoom_row['roomNumber']);

        $roomList .= '<option value="' . $Camping_id . '" ' . ($Camping_row['id'] == $mycompingid ? 'selected' : '') . '>' . $Camping_row['campingName'] . '</option> ';
        // $roomList .= '<option value="'.$eachRoomid.'" '.($eachRoom_row['id']==$eachroomselected?'selected':'').'>'.$eachRoom_row['id'].' = '.$eachroomselected.'</option> ';
    }
    echo $roomList;
    // end add new 
} elseif ($cmd == 'myroomList') {
    // for room
    $roomid = post('roomid');

    $resultcomping = exec_query_utf8("SELECT tblcamping.id FROM tblcamping JOIN tblcampingroom ON tblcamping.id = tblcampingroom.campingid WHERE tblcampingroom.id = $roomid");

    while ($Comping_row = mysqli_fetch_assoc($resultcomping)) {
        $mycompingid = $Comping_row['id'];
    }

    $roomList = '<option value="0">--- 선?? ---</option>';
    $Room_qry = exec_query_utf8("SELECT * FROM tblcampingroom WHERE campingid=$mycompingid AND active=1");


    while ($Room_row = mysqli_fetch_assoc($Room_qry)) {
        $Roomid = $Room_row['id'];
        //$eachRoomName = ucfirst($eachRoom_row['useCustomName']==1?$eachRoom_row['customName']:singleCell_qry("roomName","tblcampingroom","id=".$eachRoom_row['roomid']).' '.$eachRoom_row['roomNumber']);

        $roomList .= '<option value="' . $Roomid . '" ' . ($Room_row['id'] == $roomid ? 'selected' : '') . '>' . $Room_row['roomName'] . '</option> ';
        // $roomList .= '<option value="'.$eachRoomid.'" '.($eachRoom_row['id']==$eachroomselected?'selected':'').'>'.$eachRoom_row['id'].' = '.$eachroomselected.'</option> ';
    }
    echo $roomList;
} elseif ($cmd == 'MyRoomCampingList') {
    // for camping
    $roomid = post('roomid');

    $resultcomping = exec_query_utf8("SELECT tblcamping.id FROM tblcamping JOIN tblcampingroom ON tblcamping.id = tblcampingroom.campingid WHERE tblcampingroom.id = $roomid");

    while ($Comping_row = mysqli_fetch_assoc($resultcomping)) {
        $mycompingid = $Comping_row['id'];
    }



    $roomList = '<option value="0">--- 선?? ---</option>';
    $Comping_qry = exec_query_utf8("SELECT * FROM tblcamping WHERE active = 1");


    while ($Camping_row = mysqli_fetch_assoc($Comping_qry)) {
        $Camping_id = $Camping_row['id'];
        //$eachRoomName = ucfirst($eachRoom_row['useCustomName']==1?$eachRoom_row['customName']:singleCell_qry("roomName","tblcampingroom","id=".$eachRoom_row['roomid']).' '.$eachRoom_row['roomNumber']);

        $roomList .= '<option value="' . $Camping_id . '" ' . ($Camping_row['id'] == $mycompingid ? 'selected' : '') . '>' . $Camping_row['campingName'] . '</option> ';
        // $roomList .= '<option value="'.$eachRoomid.'" '.($eachRoom_row['id']==$eachroomselected?'selected':'').'>'.$eachRoom_row['id'].' = '.$eachroomselected.'</option> ';
    }
    echo $roomList;
} else if ($cmd == 'actionDeleteReservationHistory') {
	if (isset($_SESSION['adminid'])){
		$mybookingid = $_POST['bookingid'];
    	exec_query_utf8("UPDATE tblbooking SET active = 0 WHERE id = $mybookingid");
	}else if(isset($_SESSION['userid'])){
		if($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제"){
			$selectStatus = 'Sorry access deny.';	
			echo $selectStatus;		
		}else{
			$mybookingid = $_POST['bookingid'];
			exec_query_utf8("UPDATE tblbooking SET active = 0 WHERE id = $mybookingid");
		}
	}
} else if ($cmd == 'updateChangeStatus') {
	if (isset($_SESSION['adminid'])){
		$mybookingid = $_POST['bookingid']; 
		$myActionStatus = $_POST['action'];
		$currentDate = date('Y-m-d H:i:s');
		
		if($myActionStatus=='PaymentPaid'){
			exec_query_utf8("UPDATE tblbooking SET pending = 2 , sms_auto_cancel = 1 WHERE id in (".$mybookingid.")");
		
			// start sending message
			//.....................................................................................
			//IN CASE, ADMIN CONFIRM MONEY RECIEVED BY MONEY TRANSFER.......
			//.....................................................................................
			
			$result_sms = exec_query_utf8("SELECT * FROM tblmessagetemplate WHERE title = 'confirm money transfer recieve' AND active = 1");
			$row_sms = mysqli_fetch_object($result_sms);
			
			$result_nessarry_info = exec_query_utf8("SELECT user.id AS userid, booking.checkindate AS cin, booking.checkoutdate AS cout, roomType, booking.totalPrice, booking.username AS userName, booking.phoneNumber AS phoneNumber
													 FROM tblbooking booking INNER JOIN tbluser user ON booking.userid = user.id WHERE booking.id =". $mybookingid);
			$row_nessarry_info = mysqli_fetch_object($result_nessarry_info);
			
			$clientid = $row_nessarry_info->userid;
			$room_type_id = $row_nessarry_info->roomType;
			
			$result_client = exec_query_utf8("SELECT firstname, lastname, mobile FROM tbluser WHERE id = $clientid AND active = 1");
			$row_client = mysqli_fetch_object($result_client);
			$client_name =  $row_nessarry_info->userName;
			$client_phone =  $row_nessarry_info->phoneNumber;
			//%예약자명<1>%님 %시작일<6>%(%예약기간<10>%)/%객실명<4>%예약신청 입금완료되었습니다. -%업체명<8>%-
			$message_to_client = $row_sms->message;
		
			$cin = $row_nessarry_info->cin;
			$cout = $row_nessarry_info->cout;
			
			$date1 = new DateTime($cin);
			$date2 = new DateTime($cout);
			
			$diff = $date2->diff($date1)->format("%a");
		
			$booking_period = $diff. '박';
			
			$result_company = exec_query_utf8("SELECT companyName, roomName, companyPhone FROM tbluser user INNER JOIN tblcamping camping ON user.id = camping.userid INNER JOIN tblcampingroom campingroom ON campingroom.campingid = camping.id WHERE campingroom.id = ". $room_type_id );
			$row_company = mysqli_fetch_object($result_company);
			
			$glamping_company_name = $row_company->companyName;
			$room_type_name = $row_company->roomName;
			$company_phone = str_replace(array("-"," "), "", $row_company->companyPhone);
			
			
			
			$room_type_id = $row_nessarry_info->roomType;
			
			$company_name = $row_company->companyName;
			$company_phone = str_replace(array("-"," "), "", $row_company->companyPhone);
			// %예약자명<1>%님 %시작일<6>%(%예약기간<10>%)/%객실명<4>% 무통장처리완료
			$message_to_company = $row_sms->messagetocompany;
			
			$client_name = trim($client_name);
							
			$checkin_date = date_create($cin);
		
			$checkin_date =  date_format($checkin_date,"m-d");
			
			
			$room_type_name = str_replace( " ","", $room_type_name);
			
			$room_type_name = substr( $room_type_name, 0, 5*5);
			
			
			$message_to_client = str_replace(  
			array("%예약자명<1>%","%시작일<6>%","%예약기간<10>%","%객실명<4>%", "%phone_number%"), 
			array( $client_name, $checkin_date, $booking_period, $room_type_name , $company_phone ), 
			$message_to_client);			
			
			$message_to_company = str_replace(
			array("%예약자명<1>%","%시작일<6>%","%예약기간<10>%","%객실명<4>%", '%total_price%', "%phone_number%"),
			array( $client_name, $checkin_date, $booking_period, $room_type_name, $row_nessarry_info->totalPrice, $client_phone),
			$message_to_company);
			
			// sending message to client by glamping company
			sendSMS_05_mar_15($message_to_client, $client_name, $client_phone, $default_sms_sender );
			// sending message to glamping company by client
			sendSMS_05_mar_15($message_to_company, $company_name, $company_phone , $default_sms_sender );
			
			//sent email to admin
			booking_mail ('(Confirm Money Recieved with multiple(id:'.$mybookingid.'))'.$message_to_client);
			booking_mail ('(Confirm Money Recieved with multiple(id:'.$mybookingid.'))'.$message_to_company, 2);
			
		}else if($myActionStatus=='cancel'){
			exec_query_utf8("UPDATE tblbooking SET pending = 3 , sms_auto_cancel = 1 WHERE id in (".$mybookingid.")");
			//.....................................................................................
			//IN CASE, AUTOMATICALLY CANCEL OF RESERVATION
			//.....................................................................................
			
			$result_sms = exec_query_utf8("SELECT * FROM tblmessagetemplate WHERE title = 'admin cancel' AND active = 1");
			$row_sms = mysqli_fetch_object($result_sms);
			
			$result_nessarry_info = exec_query_utf8("SELECT user.id AS userid, booking.checkindate AS cin, booking.checkoutdate AS cout, roomType, booking.username as userName, booking.phoneNumber as phoneNumber
													 FROM tblbooking booking INNER JOIN tbluser user ON booking.userid = user.id WHERE booking.id =". $mybookingid);
			$row_nessarry_info = mysqli_fetch_object($result_nessarry_info);
			//.............plese fix this value
			$clientid = $row_nessarry_info->userid;
			
			$result_client = exec_query_utf8("SELECT firstname, lastname, mobile FROM tbluser WHERE id = $clientid AND active = 1");
			$row_client = mysqli_fetch_object($result_client);
			$client_name = $row_nessarry_info->userName;
			$client_phone = $row_nessarry_info->phoneNumber;
			
			//.............plese fix this value
			$room_type_id = $row_nessarry_info->roomType;
			
			$result_company = exec_query_utf8("SELECT companyName, roomName, companyPhone FROM tbluser user INNER JOIN tblcamping camping ON user.id = camping.userid INNER JOIN tblcampingroom campingroom ON campingroom.campingid = camping.id WHERE campingroom.id = ". $room_type_id );
			$row_company = mysqli_fetch_object($result_company);
			
			$company_name = $row_company->companyName;
			$company_phone = str_replace(array("-"," "), "", $row_company->companyPhone);
			//%예약자명<1>%님 %시작일<6>%(%예약기간<10>%)/%객실명<4>%예약 미입금으로 자동취소되었습니다.
			$message_to_company = $row_sms->messagetocompany;
			
			//.............please fix this value
			$cin = $row_nessarry_info->cin;
			$cout = $row_nessarry_info->cout;
			
			$date1 = new DateTime($cin);
			$date2 = new DateTime($cout);
			
			$diff = $date2->diff($date1)->format("%a");
			
			$booking_period = ($diff -1). '박';
			$room_type_name = $row_company->roomName;
			$glamping_company_name = $company_name;
			
			$message_to_company = str_replace(
								array("%예약자명<1>%","%시작일<6>%","%예약기간<10>%","%객실명<4>%"),
								array( $client_name, $cin, $booking_period, $room_type_name),
								$message_to_company);
								
			//%예약자명<1>%님 %시작일<6>%(%예약기간<10>%)/%객실명<4>%예약 미입금으로 자동취소되었습니다.
			$message_to_client = $row_sms->message;
			
			//.............please fix this value
			
			
			
			$message_to_client = str_replace( 
				array("%예약자명<1>%","%시작일<6>%","%예약기간<10>%","%객실명<4>%", "%업체명<8>%"), 
				array( $client_name, $cin, $booking_period, $room_type_name , $glamping_company_name ), 
				$message_to_client
				);
				
			$message_to_company .= "(".$client_phone.")";
			
			// sending message to client by glamping company
			sendSMS_05_mar_15($message_to_client, $client_name, $client_phone, $row_sms->callback==''? $company_phone : str_replace(array("-"," "), "", $row_sms->callback) );
			// sending message to glamping company by client
			sendSMS_05_mar_15($message_to_company, $company_name, $company_phone , $default_sms_sender );
			
			//sent email to admin
			booking_mail ('(Admin Cancel with multiple(id:'.$mybookingid.'))'.$message_to_client);
			booking_mail ('(Admin Cancel with multiple(id:'.$mybookingid.'))'.$message_to_company, 2);
			
		}
		else if($myActionStatus=='Pending'){
			exec_query_utf8("UPDATE tblbooking SET pending = 0 , sms_auto_cancel = 0 , delaypaymentdate = '".$currentDate."' WHERE id in (".$mybookingid.")");	
		}else if($myActionStatus=='PaymentNotYetPaid'){
			exec_query_utf8("UPDATE tblbooking SET pending = 1 , sms_auto_cancel = 0 , delaypaymentdate = '".$currentDate."' WHERE id in (".$mybookingid.")");	
		}
	}else{
		$selectStatus = 'Sorry access deny.';	
		echo $selectStatus;
	}
}else if ($cmd == 'multiActionReservationHistory') {
	if (isset($_SESSION['adminid'])){
		$mybookingid = $_POST['bookingid'];
		
		exec_query_utf8("UPDATE tblbooking SET active = 0 WHERE id in (".$mybookingid.")");
	}else{
		$selectStatus = 'Sorry access deny.';	
		echo $selectStatus;
	}
}else if ($cmd == 'editReservationHistory') {
    $id = post('id');

    $data = array();
    $location_qry = exec_query_utf8("SELECT * FROM tblbooking WHERE id=$id LIMIT 1");
    while ($bookingrow = mysqli_fetch_assoc($location_qry)) {
		
		$paymentMethod = $bookingrow['paymentmethod'];
		// first explode ] sign
		$exploadPaymentMethod = explode("]", $paymentMethod);
		$exploadPaymentMethod[0]; 
		// second explode [ sign from $exploadPaymentMethod
		$exploadPaymentMethod2 = explode("[", $exploadPaymentMethod[0]);
		$exploadPaymentMethod2[1]; 
		if($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제"){
			$reservationListString .= $paymentMethod;	
		}else if ($exploadPaymentMethod2[1]=="다른 방법" || $exploadPaymentMethod2[1]=="기타결제"){
			$reservationListString .= $exploadPaymentMethod2[1];
		}else if (startsWith($paymentMethod, 'ezwel-') || endsWith($paymentMethod, '-ezwel') ){
			$reservationListString .= 'ezwel';	
		}else{
			$reservationListString .= $paymentMethod;	
		}
		
        $data = array('username' => $bookingrow['username'], 'phoneNumber' => $bookingrow['phoneNumber'], 'bookemail' => $bookingrow['bookemail'], 'charge_price' => $bookingrow['charge_price'], 'charge_reason' => $bookingrow['charge_reason'], 'paymentmethod' => $paymentMethod);
    }
    echo json_encode($data);
	
}else if ($cmd == 'EditUserInfoReservationHistory') {
    $bookingid = post('bookingid');
    $username = post('username');
    $phoneNumber = post('phoneNumber');
    $bookemail = post("bookemail");
	$chargePrice = post('chargePrice');
    $chargeReason = post("chargeReason");
	
	//$cate_qrys = exec_query_utf8("SELECT code FROM tblportaladmin admin INNER JOIN tblcategory cate ON admin.levelid = cate.id WHERE admin.id=".$_SESSION['adminid']."");	
	
	$admin_level = '' ;
	while($cate_rows = mysqli_fetch_assoc($cate_qrys)){
		$admin_level = $cate_rows['code'] ;
	}
	
	$update_booking_sql = "UPDATE tblbooking SET username = '$username', phoneNumber='$phoneNumber', bookemail='$bookemail', charge_price='$chargePrice', charge_reason='$chargeReason' where id = $bookingid";					
	/*if ( $admin_level != 'Level 1'){*/
		//$update_booking_sql = "UPDATE tblbooking SET username = '$username', phoneNumber='$phoneNumber', bookemail='$bookemail' where id = $bookingid";	
	//}
    exec_query_utf8($update_booking_sql);
    $resultUserinformation = exec_query_utf8("SELECT * from tblbooking WHERE tblbooking.id = $bookingid LIMIT 1");
        while ($infoOfUser_Row = mysqli_fetch_assoc($resultUserinformation)) {
            $userUsername = $infoOfUser_Row['username'];
            $PhoneNumberUser = $infoOfUser_Row['phoneNumber'];
            $EmailUser = $infoOfUser_Row['bookemail'];
			$chargePrice = $infoOfUser_Row['charge_price'];
			$chargeReason = $infoOfUser_Row['charge_reason'];
			
			$extraAdult = explode('-', $infoOfUser_Row['extraAdult']);
			$children = $infoOfUser_Row['children'];
			$extraChildren = explode('-', $infoOfUser_Row['extraChildren']);
			$paymentMethod = $infoOfUser_Row['paymentmethod'];
			$exploadPaymentMethod = explode("]", $paymentMethod);
			$exploadPaymentMethod[0]; 
			// second explode [ sign from $exploadPaymentMethod
			$exploadPaymentMethod2 = explode("[", $exploadPaymentMethod[0]);
			$exploadPaymentMethod2[1]; 
			$re_paymentMothd = '';
			if($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제"){
				$re_paymentMothd = $paymentMethod;	
			}else if(!($paymentMethod=="신용카드" || $paymentMethod=="계좌이체" || $paymentMethod=="무통장결제")){
				$re_paymentMothd = "기타결제";
			}else{
				$re_paymentMothd = $exploadPaymentMethod2[1];
			}
			if($chargePrice != 0){
				if($chargeReason == ""){
					$chargeReasons = "가격변경이유: no";
				}else{
					$chargeReasons = "가격변경이유: ".$chargeReason;
				}
			}else{
				$chargeReasons = "";	
			}
            $paymentMethod = $infoOfUser_Row['paymentmethod'];
            $passingUserinfo = '<p>고객명: ' . $userUsername . '</p>
                    <p>전화번호: ' . $PhoneNumberUser . ' </p>
                    <p>이메일: ' . $EmailUser . ' </p>
                    <p>결제수단: '.$paymentMethod.' </p>
                    <p>' . $chargeReasons . ' </p>';
					
					 
			$sub_tabe_reservationHistory = '
				<table class="table table-bordered table-hover" style="background-color:#f5f5f5;font-size:18px;">
							<thead>
								<tr style="text-align:center;">
									<td>체크인</td>
									<td>체크아웃</td>
									<td>고객명</td>
									<td>전화번호</td>
									<td>전체인원</td>
									<td>성인추가</td>
									<td>어린이추가</td>
									<td>결제수단</td>
								</tr>
							</thead>
							<tbody> 
								<tr style="text-align:center;">
									<td>'.date('Y-m-d', strtotime($infoOfUser_Row['checkinDate'])).'</td>
									<td>'.date('Y-m-d', strtotime($infoOfUser_Row['checkoutDate'])).'</td>
									<td>'.$userUsername.'</td>
									<td>'.$PhoneNumberUser.'</td>
									<td>'.($infoOfUser_Row['adult'] == 0 ? '2' : $infoOfUser_Row['adult']).' 명</td>
									<td>'.$extraAdult[0].'명</td>
									<td>'.$extraChildren[0].'명</td>
									<td>'.$re_paymentMothd.'</td>
								</tr>
							</tbody>
						</table>
					</div>
			';
        }
		$data = array('list_passingUserinfo' => $passingUserinfo, 'list_sub_tabe_reservationHistory' => $sub_tabe_reservationHistory);
    echo json_encode($data);
} 
// update pending
elseif ($cmd == 'UpdPendingReservationHistory') {
	if (isset($_SESSION['adminid'])){
		$bookingid = $_POST['bookingid'];
		$currentDate = date('Y-m-d H:i:s');
		
		exec_query_utf8("UPDATE tblbooking SET pending = 0 , delaypaymentdate = '".$currentDate."', sms_auto_cancel = 0 WHERE id = $bookingid");
		
		$resultUserinformation = exec_query_utf8("SELECT pending from tblbooking WHERE id = $bookingid LIMIT 1");
        while ($selectPending_Row = mysqli_fetch_assoc($resultUserinformation)) {
			
            $selectStatus = $selectPending_Row['pending'];
					
        }
    }else{
		$selectStatus = 'Sorry access deny.';	
	}
   echo $selectStatus;
} 
// payment not yet paid
else if ($cmd == 'UpdPaymentNotYetPaid') {
	if (isset($_SESSION['adminid'])){
		$bookingid = $_POST['bookingid'];
		$currentDate = date('Y-m-d H:i:s');
		
		exec_query_utf8("UPDATE tblbooking SET pending = 1 , delaypaymentdate = '".$currentDate."', sms_auto_cancel = 0 WHERE id = $bookingid");
		
		$resultPaymentNotYetPaid = exec_query_utf8("SELECT pending from tblbooking WHERE id = $bookingid LIMIT 1");
        while ($selectPaymentNotYetPaid_Row = mysqli_fetch_assoc($resultPaymentNotYetPaid)) {
			
            $selectStatus = $selectPaymentNotYetPaid_Row['pending'];
					
        }
    }else{
		$selectStatus = 'Sorry access deny.';	
	}
   echo $selectStatus;
}
// payment paid
else if ($cmd == 'UpdPaymentPaid') {
	if (isset($_SESSION['adminid'])){
		$bookingid = $_POST['bookingid'];
		
		$bookingid = $_POST['bookingid'];
		$client_phone = '';
		$total_prcie = 0;
		$resultPaymentPaid = exec_query_utf8("SELECT pending,phoneNumber,totalPrice from tblbooking WHERE id = $bookingid LIMIT 1");
        while ($selectPaymentPaid_Row = mysqli_fetch_assoc($resultPaymentPaid)) {
			
            $selectStatus = $selectPaymentPaid_Row['pending'];
			$client_phone = $selectPaymentPaid_Row['phoneNumber'];
			$total_prcie = $selectPaymentPaid_Row['totalPrice'];
					
        }
    
	   //echo $selectStatus;
		
		// start sending message
		//.....................................................................................
		//IN CASE, ADMIN CONFIRM MONEY RECIEVED BY MONEY TRANSFER.......   
		//.....................................................................................
		
		$result_sms = exec_query_utf8("SELECT * FROM tblmessagetemplate WHERE title = 'confirm money transfer recieve' AND active = 1");
		$row_sms = mysqli_fetch_object($result_sms);
		
		$result_nessarry_info = exec_query_utf8("SELECT userid, checkindate AS cin, checkoutdate AS cout, username as userName, roomType FROM tblbooking WHERE id =". $bookingid);
		$row_nessarry_info = mysqli_fetch_object($result_nessarry_info);
		
		$clientid = $row_nessarry_info->userid;
		$room_type_id = $row_nessarry_info->roomType;
		
		$result_client = exec_query_utf8("SELECT firstname, lastname, mobile FROM tbluser WHERE id = $clientid AND active = 1");
		$row_client = mysqli_fetch_object($result_client);
		
		$client_name = $row_nessarry_info->userName;
		
		//%예약자명<1>%님 %시작일<6>%(%예약기간<10>%)/%객실명<4>%예약신청 입금완료되었습니다. -%업체명<8>%-
		$message_to_client = $row_sms->message;
	
		$cin = $row_nessarry_info->cin;
		$cout = $row_nessarry_info->cout;
		
		$date1 = new DateTime($cin);
		$date2 = new DateTime($cout);
	
		$diff = $date2->diff($date1)->format("%a"); 
	
		$booking_period = $diff. '박';
		
		$result_company = exec_query_utf8("SELECT companyName, roomName, companyPhone FROM tbluser user INNER JOIN tblcamping camping ON user.id = camping.userid INNER JOIN tblcampingroom campingroom ON campingroom.campingid = camping.id WHERE campingroom.id = ". $room_type_id);
		$row_company = mysqli_fetch_object($result_company);
		
		$glamping_company_name = $row_company->companyName;
		$room_type_name = $booking_row['roomName'];
		$company_phone = str_replace(array("-"," "), "", $row_company->companyPhone);
		
		$client_name = trim($client_name);
				
		$checkin_date = date_create( $cin );
	
		$checkin_date =  date_format($checkin_date,"m-d");
		
		
		$room_type_name = str_replace( " ","", $room_type_name);
		
		$room_type_name = substr( $room_type_name, 0, 5*5);
		
		//%예약자명<1>%님 %시작일<6>%(%예약기간<10>%)/%객실명<4>%예약신청 입금완료되었습니다. 글램핑장연락처 : %phone_number%
		
		$message_to_client = str_replace( 
		array("%예약자명<1>%"," %시작일<6>%","%예약기간<10>%","%객실명<4>%", "%phone_number%"), 
		array( $client_name, $checkin_date, $booking_period.'', $room_type_name , $company_phone ), 
		$message_to_client);
		
		$room_type_id = $row_nessarry_info->roomType;
		
		// [코리아글램핑]%시작일<6>%(%예약기간<10>%),%예약자명<1>%님(%phone_number%),%객실명<4>%<%total_price%원> 예약완료
		$message_to_company = $row_sms->messagetocompany; 
		
		$message_to_company = str_replace(
		array("%예약자명<1>%","%시작일<6>%","%예약기간<10>%","%객실명<4>%", '%total_price%', "%phone_number%"),
		array( $client_name, $checkin_date, $booking_period, $room_type_name, $total_prcie, $client_phone),
		$message_to_company);
		
		
		// sending message to client by glamping company
		sendSMS_05_mar_15($message_to_client, $client_name, $client_phone, $default_sms_sender );
		// sending message to glamping company by client
		sendSMS_05_mar_15($message_to_company, $glamping_company_name, $company_phone , $default_sms_sender );
		
		//exec_query_utf8("UPDATE tblbooking SET pending = 2, sms_auto_cancel = 1 WHERE id = $bookingid");
		
		exec_query_utf8("UPDATE tblbooking SET pending = 2, sms_auto_cancel = 1 WHERE id = $bookingid");
		
		//sent email to admin
		booking_mail ('(Confirm Money Recieved(id:'.$bookingid.'))'.$message_to_client);
		booking_mail ('(Confirm Money Recieved(id:'.$bookingid.'))'.$message_to_company, 2);
			
	}else{
		$selectStatus = 'Sorry access deny.';	
	}
	echo $selectStatus;
	
	
}
// cancel booking
else if ($cmd == 'UpdCancelBooking') {
	if (isset($_SESSION['adminid'])){
		$bookingid = $_POST['bookingid'];
		//.....................................................................................
		//IN CASE, AUTOMATICALLY CANCEL OF RESERVATION
		//.....................................................................................
		
		$result_sms = exec_query_utf8("SELECT * FROM tblmessagetemplate WHERE title = 'admin cancel' AND active = 1");
		$row_sms = mysqli_fetch_object($result_sms);
		
		$result_nessarry_info = exec_query_utf8("SELECT booking.checkindate AS cin, booking.checkoutdate AS cout, roomType, booking.username, booking.phonenumber
												 FROM tblbooking booking WHERE booking.id =". $bookingid);
		$row_nessarry_info = mysqli_fetch_object($result_nessarry_info);
		//.............plese fix this value
	
		$client_name = $row_nessarry_info->username;
		$client_phone = $row_nessarry_info->phonenumber;
		
		//.............plese fix this value
		$room_type_id = $row_nessarry_info->roomType;
		
		$result_company = exec_query_utf8("SELECT companyName, roomName, companyPhone FROM tbluser user INNER JOIN tblcamping camping ON user.id = camping.userid INNER JOIN tblcampingroom campingroom ON campingroom.campingid = camping.id WHERE campingroom.id = ". $room_type_id );
		$row_company = mysqli_fetch_object($result_company);
		$company_name = $row_company->companyName;
		$company_phone = str_replace(array("-"," "), "", $row_company->companyPhone);
		//%예약자명<1>%님 %시작일<6>%(%예약기간<10>%)/%객실명<4>%예약 미입금으로 자동취소되었습니다.
		$message_to_company = $row_sms->messagetocompany;
		
		//.............please fix this value
		$cin = $row_nessarry_info->cin;
		$cout = $row_nessarry_info->cout;
		
		$date1 = new DateTime($cin);
		$date2 = new DateTime($cout);
		
		$diff = $date2->diff($date1)->format("%a");
		
		$booking_period = $diff . '박';
		$room_type_name = $row_company->roomName;
		$glamping_company_name = $company_name;
		
		$message_to_company = str_replace(
							array("%예약자명<1>%","%시작일<6>%","%예약기간<10>%","%객실명<4>%"),
							array( $client_name, $cin, $booking_period, $room_type_name),
							$message_to_company);
							
		//%예약자명<1>%님 %시작일<6>%(%예약기간<10>%)/%객실명<4>%예약 미입금으로 자동취소되었습니다.
		$message_to_client = $row_sms->message;
		
		//.............please fix this value
		
		
		
		$message_to_client = str_replace( 
			array("%예약자명<1>%","%시작일<6>%","%예약기간<10>%","%객실명<4>%", "%업체명<8>%"), 
			array( $client_name, $cin, $booking_period, $room_type_name , $glamping_company_name ), 
			$message_to_client
			);
			
			
		//....................................
			
				
			$message_to_company .= "(".$client_phone.")";
			
			// sending message to client by glamping company
			sendSMS_05_mar_15($message_to_client, $client_name, $client_phone, $row_sms->callback==''? $company_phone : str_replace(array("-"," "), "", $row_sms->callback) );
			// sending message to glamping company by client
			sendSMS_05_mar_15($message_to_company, $company_name, $company_phone , $default_sms_sender );
			
			//sent email to admin
			booking_mail ('(Admin Cancel(id:'.$bookingid.'))'.$message_to_client);
			booking_mail ('(Admin Cancel(id:'.$bookingid.'))'.$message_to_company, 2);
		//...................................
	
		 
		exec_query_utf8("UPDATE tblbooking SET pending = 3, sms_auto_cancel = 1 WHERE id = $bookingid AND reservation_by <> 2");
		
		$resultCancel = exec_query_utf8("SELECT id, pending, paymentmethod, reservation_by from tblbooking WHERE id = $bookingid LIMIT 1");
		$reservation_by = 1; // koreaglamping
		$method = 'other';
        while ($selectCancel_Row = mysqli_fetch_assoc($resultCancel)) {
			
            $selectStatus = $selectCancel_Row['pending'];
			$reservation_by = $selectCancel_Row['reservation_by'];
			
			if ( $reservation_by == 2 ){
				
				$_SESSION['ezwel_cancel_booking_id'] =  $selectCancel_Row['id'];
				$spl = explode("-", $selectCancel_Row['paymentmethod']);
				
				$order_num = 'no';
				if ( $spl[1] != 'no' ){
					$order_num = $spl[1];
				}
				$asp_order_num = 'no';
				if ( $spl[2] != 'no' ){
					$asp_order_num = $spl[2];
				}
				$method = $order_num. '[--]'. $asp_order_num;
			}	
        }
		
	}else{
		$method = 'Sorry access deny.';	
	}
	
	echo $method;	
	
		
   
	
}elseif ( $cmd == 'updateCommisionStatus') {
	$booking_id = post('bookingid');
	$result = exec_query_utf8("UPDATE tblbooking SET paid_commission = not paid_commission WHERE id = $booking_id AND active = 1 ");	
	echo $result;
	
} elseif ($cmd == 'updateCampingSchedule') { // update sechedule camping 
    
   $days = '';
	if(post('day1')){
		$days .= post('day1').',';
	}
	if(post('day2')){
		$days .= post('day2').',';
	}
	if(post('day3')){
		$days .= post('day3').',';
	}
	if(post('day4')){
		$days .= post('day4').',';
	}
	if(post('day5')){
		$days .= post('day5').',';
	}
	if(post('day6')){
		$days .= post('day6').',';
	}
	if(post('day7')){
		$days .= post('day7').',';
	}
	$rowId;
    if (post('scheduleType') == 3){
		$rowId = post('roomNumberid');
	}elseif(post('scheduleType') == 2) {
		$rowId = post('roomTypeid');
	}else{
		$rowId = post('campingid');
	}
    $scheduleId = $_SESSION["scheduleID"];
    $setCompanyid = post('setCompanyid');
    $compingid = post('campingid');
    $scheduleType = post('scheduleType');
    $fromDate = date("Y-m-d", strtotime(post('fromDate')));
    $toDate = date("Y-m-d", strtotime(post('toDate')));
	$roomNumberid = post('roomNumberid');
    $note = post('scheduleNote');
    if (count($day) > 0) {
        $selectedDay_str = implode(',', $day);
    } else {
        $selectedDay_str = '';
    }
    exec_query_utf8("UPDATE tblschedule SET  userid = $setCompanyid , type= $scheduleType , rowid =$rowId , fromDate= '$fromDate', toDate='$fromDate', day='$days' ,note = '$note', datetime = '".date('Y-m-d H:i:s')."' where id = $scheduleId");
   
	$msg = 'updated';
    echo json_encode($msg);
	
} elseif ($cmd == 'addSchedule') {
    $newData = $_POST['newData'];
    extract($newData);
    $datetime = date("Y-m-d H:i:s");

    if (!isset($_SESSION['adminid'])) {
        $companyid = $company_sessionid;
    }

    $msg = '';
    $returnData = '';

    if ($companyid == 0 || $scheduleType == 0) {
        echo json_encode(array('msg' => $msg, 'data' => $returnData));
        exit;
    }

    if ($scheduleTerm == 0) {
        $fromDate = date('Y-m-d', strtotime($fromDate));
        $toDate = date('Y-m-d', strtotime($toDate));
        $period_cond = "fromDate='$fromDate',toDate='$toDate',";
    } elseif ($scheduleTerm == 1) {
        $period_cond = "";
    }
    if (count($selectedDay) > 0) {
        $selectedDay_str = implode(',', $selectedDay);
    } else {
        $selectedDay_str = '';
    }

    $appliedid = 0;
    $checkOverlap = 0;
    $selectStatement = "SELECT * FROM tblschedule WHERE userid=$companyid AND rowid = ";
    // add new one start
    //$selectStatement = "SELECT * FROM tblschedule WHERE userid=$companyid";
    // add new one end
    $select_date = " AND active=1 AND '$fromDate' <= toDate AND '$toDate' >= fromDate ";
    if ($scheduleType == 1) {
        if ($campingid > 0) {
            $appliedid = $campingid;
            $checkOverlap = mysqli_num_rows(exec_query_utf8($selectStatement . "$appliedid" . $select_date));
        }
    } elseif ($scheduleType == 2) {

        if ($roomTypeid > 0) {
            $appliedid = $roomTypeid;
            $checkOverlap = mysqli_num_rows(exec_query_utf8($selectStatement . "$appliedid" . $select_date));
            if ($checkOverlap == 0) {
                $checkOverlap = mysqli_num_rows(exec_query_utf8($selectStatement . "(SELECT campingid FROM tblcampingroom WHERE id =$appliedid LIMIT 1)" . $select_date . "AND type=1 "));
            }
        }

    } elseif ($scheduleType == 3) {
        if ($roomNumberid > 0) {
            $appliedid = $roomNumberid;
            $checkOverlap = mysqli_num_rows(exec_query_utf8($selectStatement . "$appliedid" . $select_date));
            if ($checkOverlap == 0) {
                $checkOverlap = mysqli_num_rows(exec_query_utf8($selectStatement . "(SELECT roomid FROM tblcampingeachroom WHERE id =$appliedid LIMIT 1)" . $select_date . "AND type=2 "));
                if ($checkOverlap == 0) {
                    $checkOverlap = mysqli_num_rows(exec_query_utf8($selectStatement . "(SELECT campingid FROM tblcampingroom WHERE id=(SELECT roomid FROM tblcampingeachroom WHERE id =$appliedid LIMIT 1) LIMIT 1)" . $select_date . "AND type=1 "));
                }
            }
        }
    }
    while ($Schadule_Row = mysqli_fetch_assoc($selectStatement)) {
        $MySchaduleID = $Schadule_Row['id'];

    }

    if ($appliedid > 0) {
        //check if overlap existing schedule
        if ($checkOverlap > 0) {
//                    exec_query_utf8("DELETE FROM tblschedule WHERE id = '$MySchaduleID'");
//                   // exec_query_utf8("UPDATE tblschedule SET userid=$companyid,type=$scheduleType,rowid=$appliedid,day='$selectedDay_str',$period_cond note='".addslashes($scheduleNote)."',datetime='$datetime' WHERE id = '$MySchaduleID'");		
//                     exec_query_utf8("INSERT INTO tblschedule SET userid=$companyid,type=$scheduleType,rowid=$appliedid,day='$selectedDay_str',$period_cond note='".addslashes($scheduleNote)."',datetime='$datetime'");	
//                    $msg = 'updated';
            //$msg = 'Date range is overlap to the existing schedule.';
            $msg = 'Hello world';
            $returnData = 'catchError';
            echo json_encode(array('msg' => $msg, 'data' => $returnData));
            exit;
        } else {
            exec_query_utf8("INSERT INTO tblschedule SET userid=$companyid,type=$scheduleType,rowid=$appliedid,day='$selectedDay_str',$period_cond note='" . addslashes($scheduleNote) . "',datetime='$datetime'");
            // exec_query_utf8("INSERT INTO tblTest(id,username,password) VALUES(123,'sothorn','123')");
            $msg = 'inserted';
        }
    }


    /* if($editMenuItemid>0 and mysqli_num_rows(exec_query_utf8("SELECT id FROM tblitem WHERE type=$menuid AND itemName='$newItemName' AND id<>$editMenuItemid"))==0){
      exec_query_utf8("UPDATE tblitem SET itemName='$newItemName',type=$menuid,price=$newItemPrice,des='".addslashes($newMenuItemDes)."',datetime='$datetime',active=1 WHERE id=$editMenuItemid LIMIT 1");
      $msg = 'updated';
      }elseif(mysqli_num_rows(exec_query_utf8("SELECT id FROM tblitem WHERE type=$menuid AND itemName='$newItemName'"))==0){
      if(trim($newItemName)<>'' and $menuid>0 and $newItemPrice>0){
      exec_query_utf8("INSERT INTO tblitem SET itemName='$newItemName',type=$menuid,price=$newItemPrice,des='".addslashes($newMenuItemDes)."',datetime='$datetime'");
      $msg = 'inserted';
      }else{
      $msg = 'invalid';
      }
      }else{
      if(mysqli_num_rows(exec_query_utf8("SELECT id FROM tblitem WHERE type=$menuid AND itemName='$newItemName' AND active=0"))>0){
      exec_query_utf8("UPDATE tblitem SET price=$newItemPrice,des='".addslashes($newMenuItemDes)."',datetime='$datetime',active=1 WHERE type=$menuid AND itemName='$newItemName' LIMIT 1");
      $msg = 'updated';
      }else{$msg = 'exist';}
      } */

    $data = array('msg' => $msg, 'data' => $returnData);
    echo json_encode($data);
} elseif ($cmd == 'commissionList') {
    $getData = $_POST["getData"];
    extract($getData);

    if (!isset($_SESSION['adminid'])) {
        $companyid = $company_sessionid;
    }

    $sql_condition = '';
    $areaid_array = array();
    if ($companyid > 0) {
        $sql_condition.=($sql_condition == '' ? '' : ' AND') . " roomNumberid IN (SELECT id FROM tblcampingeachroom WHERE roomid IN (SELECT id FROM tblcampingroom WHERE campingid IN (SELECT id FROM tblcamping WHERE userid = '$companyid')))";
    }
    if ($sql_condition <> '') {
        $sql_condition .= ' AND';
    }

    $commissionListString = '';
    $i = 1;
    $totalAmount = 0;
    $totalCommission = 0;
	 $totalDeductCommission = 0;
    if ($termMonthYear == '') {
        $termMonthYear = date("Y-m");
    }
    if ($commissionTerm == 2) {
        $termMonthYear = $termMonthYear . '-20'; //any day in 2nd term
    } else {
        $termMonthYear = $termMonthYear . '-10'; //any day in 1nd term
    }
    $commissionTerm = commissionPeriod($termMonthYear);
    $startDayTerm = $commissionTerm['from'];
    $endDayTerm = $commissionTerm['to'];
    $booking_qry = exec_query_utf8("SELECT * FROM tblbooking WHERE $sql_condition bookingDate >= '$startDayTerm 00:00:00' AND bookingDate <= '$endDayTerm 23:59:59' AND pending<>3 AND active=1"); 
    while ($booking_row = mysqli_fetch_assoc($booking_qry)) {
        $bookingDate = $booking_row['bookingDate'];
        $roomNumberid = $booking_row['roomNumberid'];
		$result_user = exec_query_utf8("SELECT * FROM tbluser WHERE id=(SELECT userid FROM tblcamping WHERE id=(SELECT campingid FROM tblcampingroom WHERE id=(SELECT roomid FROM tblcampingeachroom WHERE id=$roomNumberid)))");
		$row_user = mysqli_fetch_object($result_user);
       // $userid = singleCell_qry("id", "tbluser", "id=(SELECT userid FROM tblcamping WHERE id=(SELECT campingid FROM tblcampingroom WHERE id=(SELECT roomid FROM tblcampingeachroom WHERE id=$roomNumberid)))");
       // $commissionRate = singleCell_qry("rate", "tblcommissionsetting", "datetime<='$bookingDate' AND userid=$userid ORDER BY datetime DESC LIMIT 1");
	   $userid = $row_user->id;
	   $commissionRate = $row_user->commissionrate;
        if ($commissionRate == '') {
            $commissionRate = 10; //default or standard commission rate
        }

        $nightPrice = explode(',', $booking_row['nightPrice']);
        $itemPrice = explode(',', $booking_row['itemPrice']);
        //$adult = $booking_row['adult'];
        $extraAdult = explode('-', $booking_row['extraAdult']);
        $extraChildren = explode('-', $booking_row['extraChildren']);

        $getItemPrice = array();
        foreach ($itemPrice as $key => $value) {
            $eachItemData = explode('-', $value);
            if (count($eachItemData) > 1) {
                $getItemPrice[$eachItemData[0]] = $eachItemData[1];
            }
        }

        $totalPrice = array_sum($nightPrice) + $extraAdult[1] + $extraChildren[1] + array_sum($getItemPrice);
        $commissionAmount = $totalPrice * ($commissionRate / 100);

        $totalAmount +=$totalPrice;
        $totalCommission +=$commissionAmount;
		$totalDeductCommission += $totalPrice - $commissionAmount;
        $commissionListString .= '<tr>
			<td class="tableCellCenter">' . $i . '</td>
			<td>'.$row_user->companyName.'</td>
			<td>'.$booking_row['username'].' &gt; '.$booking_row['phoneNumber'].' &gt; '.$booking_row['paymentmethod'].'</td>
			<td>' . date("d-M-Y", strtotime($bookingDate)) . '</td>
			<td>' . date("d-M-Y", strtotime($booking_row['checkoutDate'])) . '</td>
			<td class="text-center">' . $commissionRate . '%</td>
			<td class="text-center">' .  $defaultCurrency .  number_format($totalPrice) .'</td>
			
			<td class="text-center">'  . $defaultCurrency .  number_format($totalPrice -$commissionAmount) . '</td>
			
			<td class="text-center"><span class="padding_10 moreBookingInfo_btn" title="Pending" id="commission_history_'.$booking_row['id'].'" onclick="confirmCommisionStatus('.$booking_row['id'].')" style="cursor:pointer; color:#fff;'.(($booking_row['paid_commission'] == 1)? 'color: rgb(255, 255, 255); background: rgb(0, 128, 0);' : 'color:#fff;background:red;' ).'"><i class="'.(($booking_row['paid_commission'] == 1)? 'fa fa-check-circle' : 'fa fa-exclamation-circle' ).'"></i></span> <span class="padding_10 priceTag" style="width:100px; height:40px;  ">' . $defaultCurrency .  number_format($commissionAmount) . '</span></td></tr>';
        $i++;
    }
	$total = '';
    if ($commissionListString == '') {
        $commissionListString = '<tr><td colspan="9" style="text-align:center; color:#c0434d;"><i class="fa fa-frown-o"></i> 해당결과가 존재하지 않습니다</td></tr>';
    }else{
    $total = '<tr style="background:#e7f7dd; font-weight:bold;"><td colspan="6" style="text-align:right;border:none;">Total Amounts</td>
				<td style="background-color:blue;color:white;text-align:center;">' .  $defaultCurrency . number_format($totalAmount) . '</td>
				<td style="background-color:red;color:white;text-align:center;">' .  $defaultCurrency . number_format($totalDeductCommission) .  '</td>
				<td style="background-color:#0079C9; text-align:center;color:white;">' . $defaultCurrency . number_format($totalCommission) . '</td>
			  </tr>';
	}
    echo json_encode(array('list' => $commissionListString, 'total' => $total));
	
} elseif ($cmd == 'updateScheduleStatus') {
    $rowid = $_POST['rowid'];
    exec_query_utf8("UPDATE tblschedule SET active = NOT active WHERE id=$rowid LIMIT 1");

    echo '';
} elseif ($cmd = 'iniModifySchedule') {
    $rowid = $_POST['rowid'];

    $data = array();
    $schedule_qry = exec_query_utf8("SELECT * FROM tblschedule WHERE tblschedule.id=$rowid LIMIT 1");
    while ($schedule_row = mysqli_fetch_assoc($schedule_qry)) {
        $banDays = explode(',', $schedule_row['day']);
        $data = array('userid' => $schedule_row['userid'], 'type' => $schedule_row['type'], 'rowid' => $schedule_row['rowid'], 'fromDate' => $schedule_row['fromDate'], 'toDate' => $schedule_row['toDate'], 'banDays' => $banDays, 'note' => $schedule_row['note'], 'scheduleid' => $schedule_row['id']);
        $_SESSION["scheduleID"] = $schedule_row['id'];
    }
    echo json_encode($data);
} elseif ($cmd == 'GlobalsSessionOrVariable') {
    $scheduleId = $_POST['scheduleID'];
    $_SESSION["scheduleID"] = 1; //$scheduleId;
	
} 

function reservationlist_callback($a, $b) {
    return $a['night'] - $b['night'];
}

function reservationlist_callback2($var) {
    return $var['quantity'];
}
function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}
function endsWith($haystack, $needle) {
    // search forward starting from end minus needle length characters
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
}

	
?>
