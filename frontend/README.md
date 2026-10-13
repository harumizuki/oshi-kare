# 推しカレ Frontend

推しカレのReact SPAです。Vite、React Router、JavaScript、CSSで構成しています。

## 必要環境

- Node.js 20.19以上、または22.12以上
- npm

## セットアップ

```bash
cd frontend
npm install
```

## 開発サーバー

```bash
npm run dev
```

起動後、ターミナルに表示されるURL（通常は `http://localhost:5173`）をブラウザで開いてください。

## 本番用ビルド

```bash
npm run build
```

ビルド結果は `dist/` に出力されます。

## ビルド結果の確認

```bash
npm run preview
```

現時点ではLaravel APIとの連携や認証機能は実装していません。
