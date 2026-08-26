const APP_NAME = 'Clyvero ERP';

/**
 * Persistent, non-interactive brand layer for screenshots and printed pages.
 */
export function AppWatermark() {
  return (
    <div className="app-watermark" aria-hidden="true">
      {Array.from({ length: 18 }, (_, index) => (
        <span key={index}>{APP_NAME}</span>
      ))}
    </div>
  );
}