import {
	Box,
	Button,
	CircularProgress,
	Stack,
	Typography,
} from "@mui/material";
import { memo, Suspense, useCallback, useState } from "react";
import LogFileList from "./LogFileList";
import LogContentViewer from "./LogContentViewer";
import { adminApiClient, LogContentResponse } from "../api/adminApi";
import { ErrorBoundary } from "./ErrorBoundary";

interface LogViewerProps {
	onClickBack: () => void;
}

export default memo<LogViewerProps>(function LogViewer({ onClickBack }) {
	const [selectedLog, setSelectedLog] = useState<string | null>(null);
	const [lines, setLines] = useState<number>(1000);
	const [logsPromise, setLogsPromise] = useState(() =>
		adminApiClient.getLogsList()
	);
	const [contentPromise, setContentPromise] =
		useState<Promise<LogContentResponse> | null>(null);

	const handleSelectLog = useCallback(
		(logName: string) => {
			setSelectedLog(logName);
			setContentPromise(adminApiClient.getLogContent(logName, lines));
		},
		[lines]
	);

	const handleLinesChange = useCallback((newLines: number) => {
		setLines(Math.max(1, Math.min(10000, newLines)));
	}, []);

	const handleLogsRetry = useCallback(() => {
		setLogsPromise(adminApiClient.getLogsList());
	}, []);

	const handleContentRetry = useCallback(() => {
		if (!selectedLog) return;
		setContentPromise(adminApiClient.getLogContent(selectedLog, lines));
	}, [selectedLog, lines]);

	return (
		<Box sx={{ p: 2 }}>
			<Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 2 }}>
				<Button variant="outlined" onClick={onClickBack}>
					戻る
				</Button>
				<Typography variant="h5">ログビューア（管理者専用）</Typography>
			</Stack>

			<ErrorBoundary onReset={handleLogsRetry}>
				<Suspense
					fallback={
						<Box sx={{ display: "flex", justifyContent: "center", my: 4 }}>
							<CircularProgress />
						</Box>
					}
				>
					<LogFileList
						logsPromise={logsPromise}
						onSelectLog={handleSelectLog}
						selectedLog={selectedLog}
					/>
				</Suspense>
			</ErrorBoundary>

			{selectedLog && contentPromise && (
				<Box sx={{ mt: 2 }}>
					<ErrorBoundary onReset={handleContentRetry}>
						<Suspense
							fallback={
								<Box sx={{ display: "flex", justifyContent: "center", my: 4 }}>
									<CircularProgress />
								</Box>
							}
						>
							<LogContentViewer
								contentPromise={contentPromise}
								lines={lines}
								onLinesChange={handleLinesChange}
								onRefresh={handleContentRetry}
							/>
						</Suspense>
					</ErrorBoundary>
				</Box>
			)}
		</Box>
	);
});
