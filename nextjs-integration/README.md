# Next.js Chat Widget Integration

Tích hợp chat widget vào Next.js app (hoặc bất kỳ React app nào).

## 📦 Cài đặt

### 1. Copy files vào project

```bash
# Copy vào Next.js project
cp ChatWidget.tsx your-nextjs-app/components/
cp ChatWidget.css your-nextjs-app/components/
```

### 2. Sử dụng trong Next.js

#### App Router (Next.js 13+)

```tsx
// app/layout.tsx
import ChatWidget from '@/components/ChatWidget';

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="vi">
      <body>
        {children}
        
        {/* Chat Widget */}
        <ChatWidget 
          apiUrl="https://api.live-stream.io.vn/wp-json/visssoft-ai-chat/v1"
          visitorName="Khách hàng"
          visitorEmail=""
          visitorPhone=""
        />
      </body>
    </html>
  );
}
```

#### Pages Router (Next.js 12 và cũ hơn)

```tsx
// pages/_app.tsx
import ChatWidget from '@/components/ChatWidget';
import type { AppProps } from 'next/app';

export default function App({ Component, pageProps }: AppProps) {
  return (
    <>
      <Component {...pageProps} />
      
      {/* Chat Widget */}
      <ChatWidget 
        apiUrl="https://api.live-stream.io.vn/wp-json/visssoft-ai-chat/v1"
      />
    </>
  );
}
```

## ⚙️ Configuration

### Props

| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `apiUrl` | `string` | ✅ Yes | - | WordPress REST API base URL |
| `visitorName` | `string` | ❌ No | `''` | Tên khách hàng (nếu đã biết) |
| `visitorEmail` | `string` | ❌ No | `''` | Email khách hàng |
| `visitorPhone` | `string` | ❌ No | `''` | Số điện thoại |

### Example với user data

```tsx
'use client';

import { useSession } from 'next-auth/react';
import ChatWidget from '@/components/ChatWidget';

export default function ChatProvider() {
  const { data: session } = useSession();

  return (
    <ChatWidget 
      apiUrl={process.env.NEXT_PUBLIC_CHAT_API_URL!}
      visitorName={session?.user?.name || ''}
      visitorEmail={session?.user?.email || ''}
    />
  );
}
```

## 🔧 Environment Variables

```env
# .env.local
NEXT_PUBLIC_CHAT_API_URL=https://api.live-stream.io.vn/wp-json/visssoft-ai-chat/v1
```

## 🎨 Customization

### Thay đổi màu sắc

Edit `ChatWidget.css`:

```css
/* Primary gradient */
.vac-chat-button {
  background: linear-gradient(135deg, #YOUR_COLOR_1 0%, #YOUR_COLOR_2 100%);
}

/* Message bubble colors */
.vac-message-visitor .vac-message-bubble {
  background: linear-gradient(135deg, #YOUR_COLOR_1 0%, #YOUR_COLOR_2 100%);
}
```

### Thay đổi vị trí

```css
.vac-chat-button {
  bottom: 24px;  /* Khoảng cách từ bottom */
  right: 24px;   /* Khoảng cách từ right */
  /* Hoặc left: 24px; để hiển thị bên trái */
}
```

## 🚀 Features

- ✅ **Real-time polling** - Nhận tin nhắn mới mỗi 3 giây
- ✅ **Auto-scroll** - Tự động scroll xuống khi có tin nhắn mới
- ✅ **Unread badge** - Hiển thị số tin nhắn chưa đọc
- ✅ **Typing indicator** - Hiển thị khi AI đang trả lời
- ✅ **Persistent conversation** - Lưu conversation ID trong localStorage
- ✅ **Responsive** - Hoạt động tốt trên mobile
- ✅ **TypeScript** - Full type safety

## 🔌 API Endpoints Used

- `POST /chat/send` - Gửi tin nhắn
- `GET /chat/messages` - Lấy tin nhắn mới

## 📱 Mobile Support

Widget tự động responsive:
- Desktop: 380px width
- Mobile: Full width với padding

## 🐛 Troubleshooting

### CORS Issues

Nếu gặp lỗi CORS, cần config WordPress:

```php
// wp-config.php hoặc functions.php
header('Access-Control-Allow-Origin: https://your-nextjs-domain.com');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
```

### Visitor ID không lưu

Kiểm tra localStorage có hoạt động không:
```javascript
console.log(localStorage.getItem('vac_visitor_id'));
```

## 📄 License

MIT
