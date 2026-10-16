# 0008: バックエンドの層の分け方

- ステータス: 採用(一部を [0011](0011-sanctum-api-token.md) が置き換え)
- 日付: 2026-10-14

LaravelはJSONだけを返すAPIとして動かす。画面はNext.jsが描画する。

## 採用

`app/`を4つの層に分け、依存の向きを`Http → UseCases → Domain ← Infrastructure`に固定する。Eloquentを使ってよいのはInfrastructureだけにする(`database/`と`tests/`は除く)。依存の向きとEloquentの範囲は、Pestの`arch()`テスト(`tests/Arch/LayersTest.php`)で検査する。

```
app/
  Http/                    Controllers・Requests・Resources
  UseCases/<機能>/         1クラス1ユースケース(例: UseCases/Auth/RegisterUserUseCase.php)
  Domain/<集約>/           エンティティ・RepositoryのInterface・ドメイン例外(例: Domain/User/)
  Infrastructure/Eloquent/ EloquentモデルとRepositoryの実装
```

| 層 | 責務 | 依存してよいもの |
|---|---|---|
| Http | 入力の形式の検証、UseCaseの呼び出し、認証セッションの操作、レスポンスの組み立て | UseCases、Domain、Laravel |
| UseCases | 1つのユースケースの手順、認可、トランザクションの範囲 | Domainだけ |
| Domain | エンティティ(`readonly`クラス)、Repositoryなどのインターフェース、ドメイン例外 | PHPの標準機能だけ |
| Infrastructure | DomainのInterfaceの実装 | Domain、Laravel |

ルール:

- コントローラーとUseCaseは`__invoke`だけを公開する。コントローラー1つがルート1本に対応し、UseCase1つを呼ぶ。
- RepositoryはEloquentモデルをエンティティに詰め替えて返す。
- メールアドレスの重複など、DBを見ないと決まらない検証はUseCaseが行い、ドメイン例外を投げる。Http層がそれをステータスコードに変換する。
- 認可はUseCaseが、ユーザーIDとエンティティを比べて判定する。権限がなければ403を返す。存在を知られると困るリソース(他人の下書きの話など)は、存在しないときと同じ404を返す。ログインしているかどうかは、ルートの`auth:sanctum`で判定する。
- Http層は`Auth::id()`だけをUseCaseに渡す。ログインは`Auth::attempt()`、会員登録の直後は`Auth::loginUsingId()`で行う。
  - ログインの方法と、層の表の「認証セッションの操作」は superseded by [0011](0011-sanctum-api-token.md)
- パスワードのハッシュや現在時刻など、UseCaseがLaravelの機能を使うときは、DomainにInterfaceを置いてInfrastructureで実装する。

## 理由

層を分けると、クラスの数と定型のコード(Interface、詰め替え、ServiceProviderのバインド)が増える。このプロジェクトではそれらのコードをClaude Codeで書くため、手で書く手間が増えない。そのため、増えるコードの量より、責務ごとにクラスが分かれる利点を優先した。

## 却下した案

- Laravelの標準構成(コントローラーがEloquentを直接使う): 入口ごとに業務処理を複製することになる。
- RepositoryがEloquentモデルを返す案: 受け取った側で`save()`や遅延ロードを呼べてしまう。

## 代償

- Repositoryごとに、モデルとエンティティの詰め替えのコードが要る。
- Eloquentモデルを前提にした機能を使わない。対象は、ルートモデルバインディング、Policy、`Auth::user()`、FormRequestの`unique`・`exists`ルール、`LengthAwarePaginator`。ページネーションには、一覧と件数を持つ型をDomainに定義する。
- ログイン中のユーザーのエンティティが必要なときは、Repositoryで読み直すSELECTが1回増える。
