# Next.js Real-time ETL Logs Dashboard - Implementation Prompt

## Overview
Create a real-time ETL logs monitoring dashboard for a Next.js application that monitors background ETL processes from a CodeIgniter PHP backend.

## Backend API Requirements (to add to your existing PHP CodeIgniter API)

### 1. Real-time Logs Endpoint
```
GET /api/etl/logs/realtime
```
- Implement Server-Sent Events (SSE) endpoint that streams log updates
- Should emit events when ETL status changes or new logs are created
- Return JSON format: `{id, start_date, end_date, duration, status, total_records, offset, created_at}`

### 2. Current Status Endpoint
```
GET /api/etl/status
```
- Return current ETL process status
- Include: `{is_running, current_log_id, progress_percentage, last_update}`

### 3. Manual Trigger Endpoint
```
POST /api/etl/trigger
```
- Allow manual triggering of ETL process
- Return immediate response with process ID

### 4. Historical Logs Endpoint
```
GET /api/etl/logs?page=1&limit=10
```
- Paginated historical logs
- Include filters by date range and status

## Frontend Components to Build

### 1. Real-time Dashboard Page (`/etl-monitoring`)
- Live status indicator (Running/Idle/Failed)
- Progress bar showing current ETL progress
- Real-time log stream with auto-scroll
- Manual trigger button with confirmation dialog
- Last run statistics (duration, records processed, etc.)

### 2. Live Log Stream Component
- Auto-updating log entries with timestamps
- Color-coded status indicators:
  - 🟢 Green = success/finished
  - 🔴 Red = failed
  - 🟡 Yellow = running
  - ⚪ Gray = pending
- Expandable log details showing categories/subjects processed
- Auto-scroll to latest entries with option to pause scrolling
- Search and filter capabilities

### 3. ETL Status Card
- Current status badge with real-time updates
- Progress indicator with percentage
- Time elapsed for current run
- ETA estimation based on previous runs
- Last successful run information

### 4. Historical Logs Table
- Sortable columns (date, duration, status, records)
- Filterable by date range and status
- Pagination with infinite scroll option
- Export functionality (CSV/JSON)
- Quick actions (view details, retry failed)

### 5. Statistics Dashboard
- Average run duration chart (line chart)
- Success rate over time (area chart)
- Records processed trends (bar chart)
- Performance metrics summary cards
- System health indicators

## Technical Implementation Details

### 1. Real-time Updates
```typescript
// Use EventSource API for Server-Sent Events
const eventSource = new EventSource('/api/etl/logs/realtime');

eventSource.onmessage = (event) => {
  const logData = JSON.parse(event.data);
  updateLogState(logData);
};

// Implement fallback to polling every 5 seconds
// Handle connection failures with auto-reconnect
```

### 2. State Management
- Use **React Query/SWR** for API state management
- Implement optimistic updates for manual triggers
- Cache historical data with proper invalidation
- Real-time state synchronization

### 3. UI/UX Features
- Toast notifications for status changes
- Loading states and skeleton screens
- Responsive design for mobile monitoring
- Dark/light theme support
- Sound notifications for failures (optional)
- Keyboard shortcuts for common actions

### 4. Error Handling
- Connection loss indicators
- Retry mechanisms for failed requests
- User-friendly error messages
- Graceful degradation when real-time fails

## Data Structures & Types

```typescript
interface ETLLog {
  id: number;
  start_date: string;
  end_date?: string;
  duration?: string;
  status: 'running' | 'finished' | 'failed' | 'pending';
  total_records: number;
  offset: number;
  created_at: string;
  categories_processed?: number;
  subjects_processed?: number;
  error_message?: string;
}

interface ETLStatus {
  is_running: boolean;
  current_log_id?: number;
  progress_percentage: number;
  last_update: string;
  estimated_completion?: string;
  current_step?: string;
  performance_metrics?: {
    records_per_second: number;
    memory_usage: string;
    cpu_usage: number;
  };
}

interface ETLTriggerResponse {
  success: boolean;
  log_id: number;
  message: string;
  estimated_duration?: string;
}
```

## Key Features Checklist

### Core Functionality
- [ ] Real-time log streaming with SSE
- [ ] Manual ETL process triggering
- [ ] Historical logs with pagination
- [ ] Live status monitoring
- [ ] Progress tracking

### User Experience
- [ ] Auto-refresh every 2-3 seconds when ETL is running
- [ ] Pause/resume monitoring capability
- [ ] Real-time notifications for status changes
- [ ] Mobile-responsive design
- [ ] Accessibility features (ARIA labels, keyboard navigation)

### Data Visualization
- [ ] Historical data visualization with charts
- [ ] Performance metrics dashboard
- [ ] Trend analysis
- [ ] Export functionality for compliance/auditing

### Advanced Features
- [ ] Search and filter functionality
- [ ] Sound/visual alerts for failures
- [ ] Multiple ETL process monitoring
- [ ] Role-based access control
- [ ] System health monitoring

## Component Structure

```
app/
├── etl-monitoring/
│   ├── page.tsx                 # Main dashboard page
│   ├── components/
│   │   ├── ETLStatusCard.tsx    # Live status display
│   │   ├── LogStream.tsx        # Real-time log stream
│   │   ├── HistoricalLogs.tsx   # Historical data table
│   │   ├── StatsDashboard.tsx   # Analytics charts
│   │   ├── TriggerButton.tsx    # Manual trigger control
│   │   └── ProgressBar.tsx      # Progress visualization
│   └── hooks/
│       ├── useETLLogs.ts        # Real-time logs hook
│       ├── useETLStatus.ts      # Status monitoring hook
│       └── useETLTrigger.ts     # Manual trigger hook
```

## Implementation Steps

### Phase 1: Basic Structure
1. Set up the main dashboard page
2. Create basic ETL status card
3. Implement historical logs table
4. Add manual trigger functionality

### Phase 2: Real-time Features
1. Implement Server-Sent Events connection
2. Add live log streaming
3. Create progress tracking
4. Add real-time status updates

### Phase 3: Enhanced UX
1. Add data visualization charts
2. Implement search and filtering
3. Add notifications and alerts
4. Optimize for mobile devices

### Phase 4: Advanced Features
1. Add export functionality
2. Implement performance monitoring
3. Add system health indicators
4. Create admin controls

## Performance Considerations

- Use **React.memo** for expensive components
- Implement **virtual scrolling** for large log lists
- **Debounce** search inputs and filters
- **Lazy load** historical data
- **Cache** frequently accessed data
- **Optimize** bundle size with code splitting

## Security & Best Practices

- Validate all API responses
- Implement proper error boundaries
- Use TypeScript for type safety
- Follow Next.js security best practices
- Implement proper authentication/authorization
- Add request rate limiting awareness

## Testing Strategy

- Unit tests for hooks and utilities
- Integration tests for API interactions
- E2E tests for critical user flows
- Performance testing for real-time features
- Accessibility testing with screen readers

## Deployment Considerations

- Environment-specific API endpoints
- Real-time connection configuration
- Monitoring and alerting setup
- Performance metrics collection
- Error tracking and logging

---

**Note**: Use modern Next.js 14+ features like App Router and Server Components where appropriate. Ensure the dashboard is production-ready with proper error handling, performance optimization, and accessibility compliance. 