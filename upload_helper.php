<?php
function uploadNIC($file, $folder) {
    $allowed = ['jpg','jpeg','png'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (!in_array($ext, $allowed)) {
        return false;
    }

    $newName = uniqid() . "." . $ext;
    $path = "../uploads/" . $folder . "/" . $newName;

    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $newName;
    }
    return false;
}
?>