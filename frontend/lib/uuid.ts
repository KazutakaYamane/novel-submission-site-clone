// Laravel が作る ID は小文字の UUID。大文字を通すと、同じ作品に別のキャッシュキーとタグができ、Laravel からの破棄が届かない
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;

export function isUuid(value: string): boolean {
  return UUID.test(value);
}
