<?php

namespace App\Models;

use App\Attributes\ShardConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ShardConnection(keyColumn: 'player_id', connectionPrefix: 'mysql_shard', numberOfShards: 2)]
class PlayerScore extends Model
{
    use HasFactory;

    protected $table = 'player_scores';

    protected $fillable = [
        'player_id',
        'game_id',
        'score',
    ];

    protected $casts = [
        'player_id' => 'integer',
        'game_id' => 'integer',
        'score' => 'integer',
    ];

    // 對於複合主鍵，Laravel Eloquent 不原生支援 '$primaryKey' 為陣列。
    // 我們依賴 'setKeysForSaveQuery' 方法來處理更新和刪除。
    // 因此，這裡不需要定義 '$primaryKey' 或 '$keyType' 為陣列。
    // 讓 $primaryKey 保持其預設行為 (通常是 'id') 或不定義，
    // 然後完全依賴 setKeysForSaveQuery。
    public $incrementing = false; // 複合主鍵通常不是自增的

    /**
     * Set the keys for a save update query.
     *
     * This method is crucial for handling composite primary keys in Eloquent.
     * It instructs Eloquent how to locate the record when performing update or delete operations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        // 確保同時使用 player_id 和 game_id 來定位唯一的記錄
        $query
            ->where('player_id', '=', $this->getAttribute('player_id'))
            ->where('game_id', '=', $this->getAttribute('game_id'));

        return $query;
    }

    // 建議：如果您在資料庫遷移中將 'player_id' 和 'game_id' 定義為複合主鍵，
    // 您可以在這裡添加一個存取器 (Accessor) 或方便的語法，
    // 以便在需要時能夠方便地獲取或設定複合鍵的值，
    // 但這不是處理 Eloquent 複合主鍵的必需步驟。
    // 例如：
    // public function getCompositeKeyAttribute()
    // {
    //     return [
    //         'player_id' => $this->player_id,
    //         'game_id' => $this->game_id,
    //     ];
    // }
}
