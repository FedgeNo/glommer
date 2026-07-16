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
            $user_id = Auth::id();

            $stmt = DB::run('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'i', $user_id);
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
