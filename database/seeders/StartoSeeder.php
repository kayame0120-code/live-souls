<?php

namespace Database\Seeders;

use App\Models\GroupMember;
use App\Models\IdolGroup;
use Illuminate\Database\Seeder;

class StartoSeeder extends Seeder
{
    private const COLOR_MAP = [
        '赤' => '#E53935',
        'オレンジ' => '#FF9800',
        '黄色' => '#FDD835',
        '黄' => '#FDD835',
        '黄緑' => '#7CB342',
        '緑' => '#43A047',
        '水色' => '#29B6F6',
        '青' => '#1E88E5',
        '紫' => '#7B1FA2',
        'ピンク' => '#EC407A',
        '黒' => '#212121',
        '白' => '#FAFAFA',
    ];

    public function run(): void
    {
        foreach ($this->groups() as $groupData) {
            $group = IdolGroup::updateOrCreate(
                ['name' => $groupData['name']],
                ['status' => $groupData['status']],
            );

            foreach ($groupData['members'] as $i => $member) {
                GroupMember::updateOrCreate(
                    ['idol_group_id' => $group->id, 'name' => $member['name']],
                    [
                        'color_name' => $member['color_name'],
                        'color_hex' => self::COLOR_MAP[$member['color_name']] ?? null,
                        'source_type' => $member['source_type'],
                        'sort_order' => $i + 1,
                    ],
                );
            }
        }
    }

    private function groups(): array
    {
        return [
            [
                'name' => 'Snow Man', 'status' => null,
                'members' => [
                    ['name' => '岩本照', 'color_name' => '黄色', 'source_type' => '公式'],
                    ['name' => '深澤辰哉', 'color_name' => '紫', 'source_type' => '公式'],
                    ['name' => '渡辺翔太', 'color_name' => '青', 'source_type' => '公式'],
                    ['name' => '阿部亮平', 'color_name' => '緑', 'source_type' => '公式'],
                    ['name' => '宮舘涼太', 'color_name' => '赤', 'source_type' => '公式'],
                    ['name' => '佐久間大介', 'color_name' => 'ピンク', 'source_type' => '公式'],
                    ['name' => '向井康二', 'color_name' => 'オレンジ', 'source_type' => '公式'],
                    ['name' => '目黒蓮', 'color_name' => '黒', 'source_type' => '公式'],
                    ['name' => 'ラウール', 'color_name' => '白', 'source_type' => '公式'],
                ],
            ],
            [
                'name' => 'SixTONES', 'status' => null,
                'members' => [
                    ['name' => 'ジェシー', 'color_name' => '赤', 'source_type' => '公式'],
                    ['name' => '京本大我', 'color_name' => 'ピンク', 'source_type' => '公式'],
                    ['name' => '髙地優吾', 'color_name' => '黄', 'source_type' => '公式'],
                    ['name' => '田中樹', 'color_name' => '青', 'source_type' => '公式'],
                    ['name' => '松村北斗', 'color_name' => '黒', 'source_type' => '公式'],
                    ['name' => '森本慎太郎', 'color_name' => '緑', 'source_type' => '公式'],
                ],
            ],
            [
                'name' => 'なにわ男子', 'status' => null,
                'members' => [
                    ['name' => '西畑大吾', 'color_name' => '赤', 'source_type' => '公式'],
                    ['name' => '大西流星', 'color_name' => 'オレンジ', 'source_type' => '公式'],
                    ['name' => '道枝駿佑', 'color_name' => 'ピンク', 'source_type' => '公式'],
                    ['name' => '高橋恭平', 'color_name' => '紫', 'source_type' => '公式'],
                    ['name' => '長尾謙杜', 'color_name' => '黄色', 'source_type' => '公式'],
                    ['name' => '藤原丈一郎', 'color_name' => '青', 'source_type' => '公式'],
                    ['name' => '大橋和也', 'color_name' => '緑', 'source_type' => '公式'],
                ],
            ],
            [
                'name' => 'Travis Japan', 'status' => null,
                'members' => [
                    ['name' => '宮近海斗', 'color_name' => '赤', 'source_type' => '公式'],
                    ['name' => '中村海人', 'color_name' => '緑', 'source_type' => '公式'],
                    ['name' => '七五三掛龍也', 'color_name' => 'ピンク', 'source_type' => '公式'],
                    ['name' => '川島如恵留', 'color_name' => '白', 'source_type' => '公式'],
                    ['name' => '吉澤閑也', 'color_name' => '黄', 'source_type' => '公式'],
                    ['name' => '松田元太', 'color_name' => '青', 'source_type' => '公式'],
                    ['name' => '松倉海斗', 'color_name' => 'オレンジ', 'source_type' => '公式'],
                ],
            ],
            [
                'name' => 'Kis-My-Ft2', 'status' => null,
                'members' => [
                    ['name' => '北山宏光', 'color_name' => '赤', 'source_type' => '公式'],
                    ['name' => '横尾渉', 'color_name' => 'オレンジ', 'source_type' => '公式'],
                    ['name' => '玉森裕太', 'color_name' => '黄色', 'source_type' => '公式'],
                    ['name' => '二階堂高嗣', 'color_name' => '緑', 'source_type' => '公式'],
                    ['name' => '千賀健永', 'color_name' => '水色', 'source_type' => '公式'],
                    ['name' => '宮田俊哉', 'color_name' => '紫', 'source_type' => '公式'],
                    ['name' => '藤ヶ谷太輔', 'color_name' => 'ピンク', 'source_type' => '公式'],
                ],
            ],
            [
                'name' => 'Hey! Say! JUMP', 'status' => null,
                'members' => [
                    ['name' => '山田涼介', 'color_name' => '赤', 'source_type' => '公式'],
                    ['name' => '有岡大貴', 'color_name' => 'オレンジ', 'source_type' => '公式'],
                    ['name' => '八乙女光', 'color_name' => '黄色', 'source_type' => '公式'],
                    ['name' => '薮宏太', 'color_name' => '黄緑', 'source_type' => '公式'],
                    ['name' => '中島裕翔', 'color_name' => '水色', 'source_type' => '公式'],
                    ['name' => '伊野尾慧', 'color_name' => '青', 'source_type' => '公式'],
                    ['name' => '高木雄也', 'color_name' => '紫', 'source_type' => '公式'],
                    ['name' => '知念侑李', 'color_name' => 'ピンク', 'source_type' => '公式'],
                ],
            ],
            // NEWS: spec v2.4 §3.2 訂正表（単色）を使用。mdファイルの2色記述は無視
            [
                'name' => 'NEWS', 'status' => null,
                'members' => [
                    ['name' => '小山慶一郎', 'color_name' => '紫', 'source_type' => '公式'],
                    ['name' => '加藤シゲアキ', 'color_name' => '緑', 'source_type' => '公式'],
                    ['name' => '増田貴久', 'color_name' => '黄', 'source_type' => '公式'],
                ],
            ],
            [
                'name' => 'timelesz', 'status' => null,
                'members' => [
                    ['name' => '佐藤勝利', 'color_name' => '赤', 'source_type' => '公式'],
                    ['name' => '松島聡', 'color_name' => '緑', 'source_type' => '公式'],
                    ['name' => '菊池風磨', 'color_name' => '紫', 'source_type' => '公式'],
                    ['name' => '寺西拓人', 'color_name' => '水色', 'source_type' => '公式'],
                    ['name' => '原嘉孝', 'color_name' => '黄緑', 'source_type' => '公式'],
                    ['name' => '橋本将生', 'color_name' => 'ピンク', 'source_type' => '公式'],
                    ['name' => '猪俣周杜', 'color_name' => '黄色', 'source_type' => '公式'],
                    ['name' => '篠塚大輝', 'color_name' => '白', 'source_type' => '公式'],
                ],
            ],
            [
                'name' => 'A.B.C-Z', 'status' => null,
                'members' => [
                    ['name' => '橋本良亮', 'color_name' => '赤', 'source_type' => '公式'],
                    ['name' => '塚田僚一', 'color_name' => '黄色', 'source_type' => '公式'],
                    ['name' => '五関晃一', 'color_name' => '青', 'source_type' => '公式'],
                    ['name' => '河合郁人', 'color_name' => '紫', 'source_type' => '公式'],
                    ['name' => '戸塚祥太', 'color_name' => 'ピンク', 'source_type' => '公式'],
                ],
            ],
            [
                'name' => 'WEST.', 'status' => null,
                'members' => [
                    ['name' => '重岡大毅', 'color_name' => '赤', 'source_type' => '公式'],
                    ['name' => '桐山照史', 'color_name' => 'オレンジ', 'source_type' => '公式'],
                    ['name' => '中間淳太', 'color_name' => '黄色', 'source_type' => '公式'],
                    ['name' => '神山智洋', 'color_name' => '緑', 'source_type' => '公式'],
                    ['name' => '藤井流星', 'color_name' => '青', 'source_type' => '公式'],
                    ['name' => '濵田崇裕', 'color_name' => '紫', 'source_type' => '公式'],
                    ['name' => '小瀧望', 'color_name' => 'ピンク', 'source_type' => '公式'],
                ],
            ],
            [
                'name' => 'SUPER EIGHT', 'status' => null,
                'members' => [
                    ['name' => '丸山隆平', 'color_name' => 'オレンジ', 'source_type' => '公式'],
                    ['name' => '大倉忠義', 'color_name' => '緑', 'source_type' => '公式'],
                    ['name' => '安田章大', 'color_name' => '青', 'source_type' => '公式'],
                    ['name' => '村上信五', 'color_name' => '紫', 'source_type' => '公式'],
                    ['name' => '横山裕', 'color_name' => '黒', 'source_type' => '公式'],
                ],
            ],
            [
                'name' => 'DOMOTO', 'status' => null,
                'members' => [
                    ['name' => '堂本光一', 'color_name' => '赤', 'source_type' => '公式'],
                    ['name' => '堂本剛', 'color_name' => '青', 'source_type' => '公式'],
                ],
            ],
            [
                'name' => '嵐', 'status' => '休止',
                'members' => [
                    ['name' => '櫻井翔', 'color_name' => '赤', 'source_type' => '公式'],
                    ['name' => '二宮和也', 'color_name' => '黄色', 'source_type' => '公式'],
                    ['name' => '相葉雅紀', 'color_name' => '緑', 'source_type' => '公式'],
                    ['name' => '大野智', 'color_name' => '青', 'source_type' => '公式'],
                    ['name' => '松本潤', 'color_name' => '紫', 'source_type' => '公式'],
                ],
            ],
            [
                'name' => 'KAT-TUN', 'status' => '解散',
                'members' => [
                    ['name' => '亀梨和也', 'color_name' => 'ピンク', 'source_type' => '公式'],
                    ['name' => '上田竜也', 'color_name' => '青', 'source_type' => '公式'],
                    ['name' => '中丸雄一', 'color_name' => '紫', 'source_type' => '公式'],
                ],
            ],
        ];
    }
}
