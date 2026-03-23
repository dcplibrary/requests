<?php

declare(strict_types=1);

namespace Dcplibrary\Requests\Support;

/**
 * Icons offered on the request status form (solid + matching *-outline names).
 *
 * Every solid key must exist in {@see HeroiconsOutlinePaths::all()} so outline variants render.
 */
final class RequestStatusIconCatalog
{
    /**
     * Solid icons keyed by slug; order is not significant (see {@see solidLabels()}).
     *
     * @return array<string, string>
     */
    private static function solidDefinitions(): array
    {
        return [
            'clock'                   => 'Clock',
            'check-circle'            => 'Check Circle',
            'x-circle'                => 'X Circle',
            'exclamation-circle'      => 'Exclamation Circle',
            'question-mark-circle'    => 'Question Mark',
            'information-circle'      => 'Information',
            'arrow-path'              => 'Arrow Path',
            'pause-circle'            => 'Pause',
            'play-circle'             => 'Play',
            'stop-circle'             => 'Stop',
            'magnifying-glass'        => 'Search',
            'eye'                     => 'Eye',
            'eye-slash'               => 'Eye Slash',
            'sparkles'                => 'Sparkles',
            'shopping-bag'            => 'Shopping Bag',
            'shopping-cart'           => 'Shopping Cart',
            'truck'                   => 'Truck',
            'envelope'                => 'Envelope',
            'archive-box'             => 'Archive',
            'no-symbol'               => 'No Symbol',
            'flag'                    => 'Flag',
            'star'                    => 'Star',
            'bolt'                    => 'Bolt',
            'bell'                    => 'Bell',
            'bell-alert'              => 'Bell Alert',
            'hand-thumb-up'           => 'Thumb Up',
            'hand-thumb-down'         => 'Thumb Down',
            'bookmark'                => 'Bookmark',
            'paper-airplane'          => 'Paper Airplane',
            'document-check'          => 'Doc Check',
            'clipboard-document-list' => 'Clipboard',
            'cog-6-tooth'             => 'Settings',
            'document-text'           => 'Document',
            'book-open'               => 'Book Open',
            'user-group'              => 'User Group',
            'users'                   => 'Users',
            'circle-stack'            => 'Database',
            'barcode'                 => 'Barcode',
            'arrow-up'                => 'Arrow Up',
            'arrow-down'              => 'Arrow Down',
            'arrow-left'              => 'Arrow Left',
            'arrow-right'             => 'Arrow Right',
            'arrow-up-right'          => 'Arrow Up Right',
            'arrow-down-right'        => 'Arrow Down Right',
            'arrow-up-left'           => 'Arrow Up Left',
            'arrow-down-left'         => 'Arrow Down Left',
            'arrow-long-up'           => 'Arrow Long Up',
            'arrow-long-down'         => 'Arrow Long Down',
            'arrow-long-left'         => 'Arrow Long Left',
            'arrow-long-right'        => 'Arrow Long Right',
            'arrow-uturn-left'        => 'Arrow U-Turn Left',
            'arrow-uturn-right'       => 'Arrow U-Turn Right',
            'arrow-uturn-up'          => 'Arrow U-Turn Up',
            'arrow-uturn-down'        => 'Arrow U-Turn Down',
            'arrows-pointing-in'      => 'Arrows In',
            'arrows-pointing-out'     => 'Arrows Out',
            'arrows-right-left'       => 'Arrows Left Right',
            'arrows-up-down'          => 'Arrows Up Down',
        ];
    }

    /**
     * @return array<string, string> solid icon key => short label, sorted A–Z by label (natural, case-insensitive)
     */
    public static function solidLabels(): array
    {
        static $sorted = null;
        if ($sorted !== null) {
            return $sorted;
        }

        $labels = self::solidDefinitions();
        uksort(
            $labels,
            static fn (string $keyA, string $keyB): int => strnatcasecmp($labels[$keyA], $labels[$keyB])
        );
        /** @var array<string, string> $labels */
        $sorted = $labels;

        return $sorted;
    }

    /**
     * @return list<string> grid order: each solid followed by its outline twin
     */
    public static function gridKeysInOrder(): array
    {
        $order = [];
        foreach (array_keys(self::solidLabels()) as $key) {
            $order[] = $key;
            $order[] = $key . '-outline';
        }

        return $order;
    }

    /**
     * @return array<string, string> all selectable keys (solid + outline) => label
     */
    public static function allLabels(): array
    {
        $out = [];
        foreach (self::solidLabels() as $key => $label) {
            $out[$key]                = $label;
            $out[$key . '-outline']   = $label . ' (outline)';
        }

        return $out;
    }
}
