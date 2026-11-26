<?php
declare(strict_types=1);

function secureUpload(array $file, array $app, string $destDir): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok'=>false,'error'=>'Error al subir archivo.'];
    }
    $maxBytes = (int)($app['upload_max_mb'] ?? 5) * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxBytes) return ['ok'=>false,'error'=>'Archivo excede el tamaño máximo.'];
    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) return ['ok'=>false,'error'=>'Archivo inválido.'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $allowed = $app['allowed_upload_mime'] ?? [];
    if ($allowed && !in_array($mime, $allowed, true)) return ['ok'=>false,'error'=>'Tipo de archivo no permitido.'];
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowedExt = ['pdf','jpg','jpeg','png','webp','xlsx','csv'];
    if ($ext === '' || !in_array($ext, $allowedExt, true)) return ['ok'=>false,'error'=>'Extensión no permitida.'];
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $dest)) return ['ok'=>false,'error'=>'No se pudo guardar el archivo.'];
    return ['ok'=>true,'path'=>$dest,'name'=>$name,'mime'=>$mime,'size'=>(int)$file['size']];
}
