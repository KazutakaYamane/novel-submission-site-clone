# ADR-BACKEND: バックエンドの設計判断

- ステータス: 採用
- 最終更新: 2026-10-14

バックエンドはLaravelで、JSONだけを返すAPIとして動かす(画面はNext.jsが描画する)。このADRには、Laravelのコードの構成と、層ごとの責務についての判断を書く。

---

## コードをどの層に分けるか

**問題**: 話の公開は、作者の操作(API)と予約公開のスケジューラ(Artisanコマンド)の2か所から行われ、どちらも状態の遷移とNext.jsへの`revalidateTag`の依頼を同じ手順で行う必要がある。作品の作成も、APIとシーダーの両方から行う。Laravelの標準構成のように業務処理をコントローラーに書くと、入口ごとに同じ処理を複製することになり、認可もHTTPの入口でしか効かない。また、Eloquentモデルはどの層からでも`save()`や遅延ロードができるため、DBに触れる場所をコードの構成で限定できない。

**採用**: `app/`を次の4つの層に分け、依存の向きを`Http → UseCases → Domain ← Infrastructure`に固定する。DomainはRepositoryのInterfaceを持ち、InfrastructureがそれをEloquentで実装する(依存性逆転)。

```
app/
  Http/                 Controllers・Requests・Resources
  UseCases/<機能>/      1クラス1ユースケース(例: UseCases/Auth/RegisterUserUseCase.php)
  Domain/<集約>/        エンティティ・RepositoryのInterface・ドメイン例外(例: Domain/User/)
  Infrastructure/
    Eloquent/Models/        Eloquentモデル
    Eloquent/Repositories/  RepositoryのInterfaceの実装
```

| 層 | 責務 | 依存してよいもの |
|---|---|---|
| Http | 入力の形式の検証(FormRequest)、UseCaseの呼び出し、認証セッションの操作、レスポンスの組み立て(Resource・ステータスコード) | UseCases、Domain、Laravel |
| UseCases | 1つのユースケースの手順。認可の判定、Repositoryを使った読み書き、トランザクションの範囲 | Domainだけ |
| Domain | エンティティ(`readonly`クラス)、RepositoryやトランザクションなどのInterface、ドメイン例外 | PHPの標準機能だけ。`Illuminate\*`には依存しない |
| Infrastructure | DomainのInterfaceの実装。Eloquentモデル、`DB`・`Hash`などのファサード | Domain、Laravel |

ルール:

- シングルアクション: コントローラーとUseCaseは、どちらも公開メソッドを`__invoke`の1つだけ持つ。コントローラー1つがルート1本に対応し、原則としてUseCase1つを呼ぶ。UseCaseのクラス名は`RegisterUserUseCase`のように、末尾を`UseCase`にする。
- Eloquentの範囲: Eloquentモデル・クエリビルダー・`DB`ファサードを使ってよいのは`app/Infrastructure`だけ。Repositoryはモデルをエンティティに詰め替えて返し、Eloquentモデルを層の外に出さない。例外として、`database/`(factory・seeder・migration)と`tests/`はEloquentモデルを使ってよい。
- DBが必要な検証: メールアドレスの重複など、DBを見ないと決まらない条件は、FormRequestの`unique`・`exists`ルールでは検証しない。UseCaseがRepositoryで確かめ、ドメイン例外を投げる。Http層がドメイン例外をHTTPのステータスに変換する(フィールド単位のエラーは`ValidationException`で422にする)。
- 認可: 「作品の作者だけが話を編集できる」といった認可は、UseCaseが操作するユーザーのIDとエンティティを比べて判定する。LaravelのPolicyは使わない。権限がないときはドメイン例外を投げ、Http層が`bootstrap/app.php`の`withExceptions`でステータスに変換する。ログインしているかどうかはUseCaseでは判定せず、ルートの`auth:sanctum`で401にする。
  - 権限がないときは403を返す。
  - 存在が分かるだけで不利益が出るリソースには、権限がないときも存在しないときと同じ404を返す。例: 他人の下書きの話。存在を知られると、公開前の話数やIDが分かってしまう。
- 認証: Http層は`Auth::id()`でユーザーIDだけを取り出してUseCaseに渡し、`Auth::user()`(Eloquentモデル)は使わない。ログインは`Auth::loginUsingId()`で行う。`config/auth.php`のproviderには`Infrastructure`のEloquentモデルを指定する。
- 外部の機能: パスワードのハッシュや現在時刻など、UseCaseがLaravelの機能を必要とする場合は、DomainにInterfaceを置き、Infrastructureで実装する。InterfaceとInfrastructureの実装は、ServiceProviderでバインドする。
- 検査: 依存の向きとEloquentの範囲は、Pestの`arch()`テストで検査する。

**却下した案**:
- Laravelの標準構成(コントローラーがEloquentモデルを直接使う): 問題に書いたとおり、APIとコマンド・シーダーで業務処理を共有できず、DBに触れる場所も限定できない。
- RepositoryがEloquentモデルをそのまま返す案: 詰め替えのコードは不要になるが、受け取った側で`save()`や遅延ロードを呼べてしまい、「Eloquentを操作するのはRepositoryだけ」を型で守れない。
- RepositoryのInterfaceとデータの型をUseCases層に置く案: 複数のUseCaseが同じエンティティとRepositoryを共有するため、どのUseCaseにも属さないDomain層に置く。
- 複数のメソッドを持つService(`UserService`など)にする案: 機能が増えるとServiceのメソッドが増え、コントローラーと同じく肥大化する。1クラス1ユースケースなら、クラス名からルートと処理の対応が分かる。

**代償**:
- この規模のアプリならLaravelの標準構成で足りるという見方もある。入口が複数ある業務処理と認可を1か所にまとめるために、層を分けるコストを受け入れた。
- Eloquentモデルとエンティティの間の詰め替えのコードが、Repositoryごとに必要になる。
- Eloquentモデルを前提にしたLaravelの機能を使わない。対象は、ルートモデルバインディング、Policy、`Auth::user()`、FormRequestの`unique`・`exists`ルール、Eloquentモデルを渡すResourceである。Resourceはエンティティを受け取る。
- `Auth::loginUsingId()`は、ユーザーを読み直すSELECTを1回余分に発行する。
- ページネーションには`LengthAwarePaginator`を使わず、エンティティの一覧と件数を持つ型をDomainに定義する。
