<?php
// Province / district picker for shops. Names are stored without the
// "จังหวัด"/"อำเภอ" prefix, matching places.province / places.district.

function thaiProvinces() {
    return [
        "กรุงเทพมหานคร","กระบี่","กาญจนบุรี","กาฬสินธุ์","กำแพงเพชร","ขอนแก่น","จันทบุรี","ฉะเชิงเทรา",
        "ชลบุรี","ชัยนาท","ชัยภูมิ","ชุมพร","เชียงราย","เชียงใหม่","ตรัง","ตราด","ตาก","นครนายก",
        "นครปฐม","นครพนม","นครราชสีมา","นครศรีธรรมราช","นครสวรรค์","นนทบุรี","นราธิวาส","น่าน",
        "บึงกาฬ","บุรีรัมย์","ปทุมธานี","ประจวบคีรีขันธ์","ปราจีนบุรี","ปัตตานี","พระนครศรีอยุธยา",
        "พะเยา","พังงา","พัทลุง","พิจิตร","พิษณุโลก","เพชรบุรี","เพชรบูรณ์","แพร่","ภูเก็ต",
        "มหาสารคาม","มุกดาหาร","แม่ฮ่องสอน","ยโสธร","ยะลา","ร้อยเอ็ด","ระนอง","ระยอง","ราชบุรี",
        "ลพบุรี","ลำปาง","ลำพูน","เลย","ศรีสะเกษ","สกลนคร","สงขลา","สตูล","สมุทรปราการ",
        "สมุทรสงคราม","สมุทรสาคร","สระแก้ว","สระบุรี","สิงห์บุรี","สุโขทัย","สุพรรณบุรี","สุราษฎร์ธานี",
        "สุรินทร์","หนองคาย","หนองบัวลำภู","อ่างทอง","อำนาจเจริญ","อุดรธานี","อุตรดิตถ์","อุทัยธานี",
        "อุบลราชธานี",
    ];
}

// Strip a leading จังหวัด/จ./อำเภอ/อ./เขต so "อำเภอกมลาไสย" and "กมลาไสย" are the same.
function cleanThaiArea($s) {
    $s = trim((string)$s);
    $s = preg_replace('/^(จังหวัด|จ\.|อำเภอ|อ\.|เขต)\s*/u', '', $s);
    return mb_substr(trim($s), 0, 100, "UTF-8");
}

// Districts already used by places, per province, as suggestions for the district box.
function knownDistrictsByProvince($conn) {
    $out = [];
    try {
        $rows = $conn->query("SELECT DISTINCT province, district FROM places WHERE province <> '' AND district <> ''")->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $r) $out[trim($r["province"])][] = trim($r["district"]);
        foreach ($out as &$list) { $list = array_values(array_unique($list)); sort($list); }
    } catch (mysqli_sql_exception $e) {}
    return $out;
}

// Province <select> + district <input> with suggestions for the chosen province.
// $cls: [selectClass, inputClass] so each page keeps its own input style.
function thaiLocationFields($conn, $province, $district, $cls = ["", ""], $wrapSelect = null) {
    $provinces = thaiProvinces();
    if ($province !== "" && !in_array($province, $provinces, true)) $provinces[] = $province;
    $id = "tl" . bin2hex(random_bytes(3));
    ob_start(); ?>
    <select name="province" id="<?= $id ?>-p" class="<?= e($cls[0]) ?>">
      <option value="">เลือกจังหวัด</option>
      <?php foreach ($provinces as $pv): ?>
        <option value="<?= e($pv) ?>" <?= $pv === $province ? "selected" : "" ?>><?= e($pv) ?></option>
      <?php endforeach; ?>
    </select>
    <?php $select = ob_get_clean(); ob_start(); ?>
    <input name="district" id="<?= $id ?>-d" class="<?= e($cls[1]) ?>" list="<?= $id ?>-dl" maxlength="100"
           value="<?= e($district) ?>" placeholder="อำเภอ เช่น กมลาไสย" autocomplete="off">
    <datalist id="<?= $id ?>-dl"></datalist>
    <script>
    (function () {
      const D = <?= json_encode(knownDistrictsByProvince($conn), JSON_UNESCAPED_UNICODE) ?>;
      const p = document.getElementById('<?= $id ?>-p'), dl = document.getElementById('<?= $id ?>-dl');
      function fill() { dl.innerHTML = ''; (D[p.value] || []).forEach(d => { const o = document.createElement('option'); o.value = d; dl.appendChild(o); }); }
      p.addEventListener('change', fill); fill();
    })();
    </script>
    <?php $input = ob_get_clean();
    return [$wrapSelect ? $wrapSelect($select) : $select, $input];
}
