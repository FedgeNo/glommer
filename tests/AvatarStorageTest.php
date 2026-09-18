<?php

declare(strict_types=1);

class AvatarStorageTest extends DatabaseTestCase
{
    public function testAccountDeletionUsesIsolatedAvatarStorage(): void
    {
        $user = self::createUser();
        $directory = User::avatarDirectory($user);
        $this -> assertTrue(str_starts_with($directory, sys_get_temp_dir() . '/glommer-test-media-'));
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        $thumbnail = $directory . '/' . $user . '-thumb.jpg';
        $original = $directory . '/' . $user . '.jpg';
        file_put_contents($thumbnail, 'test thumbnail');
        file_put_contents($original, 'test original');
        $this -> assertSame('/uploads/avatars/' . UploadProcessor::shard($user) . '/' . $user . '-thumb.jpg?v=' . filemtime($thumbnail), User::avatarPath($user));
        User::delete($user);
        $this -> assertFalse(is_file($thumbnail));
        $this -> assertFalse(is_file($original));
    }

    public function testDatabaseFixturesNeverUseInstalledUploadDirectories(): void
    {
        foreach (['uploadDirectory', 'originalsDirectory'] as $name) {
            $path = (new \ReflectionProperty(UploadProcessor::class, $name)) -> getValue();
            $this -> assertTrue(str_starts_with($path, sys_get_temp_dir() . '/glommer-test-media-'));
        }
    }
}
