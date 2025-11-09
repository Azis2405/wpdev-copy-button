<?php
/**
 * WPDev Copy Button Helpers
 *
 * Kelas ini berisi fungsi-fungsi pembantu statis untuk plugin.
 *
 * @package    WPDev_Copy_Button
 * @subpackage WPDev_Copy_Button/includes
 * @author     WP Developer
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Keluar jika diakses secara langsung.
}

/**
 * Kelas WPDev_Helpers.
 *
 * Berisi koleksi metode statis yang dapat digunakan di seluruh plugin.
 */
class WPDev_Helpers
{
    /**
     * Mengambil grup pengguna dari plugin Sejoli berdasarkan ID pengguna.
     *
     * @param int $user_id ID dari pengguna WordPress.
     * @return array|null Mengembalikan array dengan 'id' dan 'name' jika berhasil, atau null jika gagal.
     */
    public static function get_sejoli_user_group(int $user_id): ?array
    {
        if (!post_type_exists('sejoli-user-group') || $user_id <= 0) {
            return null;
        }

        $group_id = get_user_meta($user_id, '_user_group', true);
        if (empty($group_id) || !is_numeric($group_id)) {
            return null;
        }

        $group_post = get_post((int) $group_id);

        if (
            !$group_post instanceof WP_Post ||
            $group_post->post_type !== 'sejoli-user-group' ||
            $group_post->post_status !== 'publish'
        ) {
            return null;
        }

        return [
            'id'   => $group_post->ID,
            'name' => $group_post->post_title,
        ];
    }

    /**
     * Mendeteksi Sistem Operasi dari string User Agent.
     *
     * @param string $user_agent String User Agent dari browser.
     * @return string Nama Sistem Operasi yang terdeteksi.
     */
    public static function get_os_from_user_agent(string $user_agent): string
    {
        $os_platform = "Unknown";
        
        $os_array = [
            '/windows nt 10/i'        => 'Windows 10',
            '/windows nt 6.3/i'       => 'Windows 8.1',
            '/windows nt 6.2/i'       => 'Windows 8',
            '/windows nt 6.1/i'       => 'Windows 7',
            '/windows nt 6.0/i'       => 'Windows Vista',
            '/windows nt 5.2/i'       => 'Windows Server 2003/XP',
            '/windows nt 5.1/i'       => 'Windows XP',
            '/windows xp/i'           => 'Windows XP',
            '/windows nt 5.0/i'       => 'Windows 2000',
            '/windows me/i'           => 'Windows ME',
            '/win98/i'                => 'Windows 98',
            '/win95/i'                => 'Windows 95',
            '/win16/i'                => 'Windows 3.11',
            '/macintosh|mac os x/i'   => 'macOS',
            '/mac_powerpc/i'          => 'Mac OS 9',
            '/linux/i'                => 'Linux',
            '/ubuntu/i'               => 'Ubuntu',
            '/iphone/i'               => 'iOS',
            '/ipod/i'                 => 'iOS',
            '/ipad/i'                 => 'iOS',
            '/android/i'              => 'Android',
            '/blackberry/i'           => 'BlackBerry',
            '/webos/i'                => 'Mobile'
        ];

        foreach ($os_array as $regex => $value) {
            if (preg_match($regex, $user_agent)) {
                $os_platform = $value;
            }
        }
        
        return $os_platform;
    }
}