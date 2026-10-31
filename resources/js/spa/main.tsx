import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router';

import { App } from '@/App';
import { ApiError } from '@/lib/api';

const client = new QueryClient({
    defaultOptions: {
        queries: {
            retry: (count, error) => !(error instanceof ApiError && error.status < 500) && count < 2,
            refetchOnWindowFocus: true,
        },
    },
});

createRoot(document.getElementById('app') as HTMLElement).render(
    <StrictMode>
        <QueryClientProvider client={client}>
            <BrowserRouter basename="/dashboard">
                <App />
            </BrowserRouter>
        </QueryClientProvider>
    </StrictMode>,
);
