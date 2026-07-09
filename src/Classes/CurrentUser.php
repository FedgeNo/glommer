<?php

declare(strict_types=1);

class CurrentUser extends User
{
    private static ?array $cachedRow = null;

    public function __construct()
    {
        parent::__construct();

        if (!Auth::check()) {
            return;
        }

        if (self::$cachedRow === null) {
            $mysqli = Database::connection();
            $user_id = Auth::id();

            $stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Users`
    WHERE `userId` = ?
');
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);

            if ($row !== null) {
                self::$cachedRow = $row;
            }
        }

        if (self::$cachedRow !== null) {
            foreach (self::$cachedRow as $key => $value) {
                $this -> $key = $value;
            }
        }
    }

    public function toDOM(): \DOMElement
    {
        $element = parent::toDOM();

        if (Auth::check() && Auth::id() === $this -> userId) {
            $uploader = new AvatarUploader();
            $element -> appendChild($uploader -> toDOM());
        }

        return $element;
    }
}
