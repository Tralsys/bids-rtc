import {
	Button,
	CircularProgress,
	Divider,
	IconButton,
	LinearProgress,
	Paper,
	Stack,
	Table,
	TableBody,
	TableCell,
	TableContainer,
	TableHead,
	TableRow,
	Typography,
} from "@mui/material";
import { ClientInfo } from "@tralsys/bids-rtc-signaling-api";
import { memo, Suspense, use, useCallback, useEffect, useState } from "react";
import { ErrorBoundary } from "react-error-boundary";
import { applicationManagementApi, clientManagementApi } from "../api";
import { useAbortOnUnmount } from "../hooks/AbortOnUnmount";
import { MdAdd, MdDelete } from "react-icons/md";
import { CLIENT_REGISTER_PARAMS } from "../constants";
import AddNewClientDialog from "./AddNewClientDialog";

type ClientManagementProps = {
	onClickBack: () => void;
};
export default memo<ClientManagementProps>(function ClientManagement({
	onClickBack,
}) {
	const abortSignal = useAbortOnUnmount();
	const [processingIdList, setProcessingIdList] = useState<number[]>([]);
	const isProcessing = 0 < processingIdList.length;
	const [isAddNewClientDialogOpen, setIsAddNewClientDialogOpen] = useState(
		CLIENT_REGISTER_PARAMS != null
	);
	const [appNamePromiseMap, setAppNamePromiseMap] = useState<
		Record<string, Promise<string>>
	>({});
	const fetchClientInfoList = useCallback(() => {
		if (abortSignal == null) {
			return;
		}

		const processingId = Math.random();
		setProcessingIdList((prev) => [...prev, processingId]);
		return clientManagementApi
			.getClientInfoList({
				signal: abortSignal,
			})
			.then((clientInfoList) => {
				const appIdSet = new Set(
					clientInfoList.map((clientInfo) => clientInfo.appId)
				);
				setAppNamePromiseMap((prev) => {
					if ([...appIdSet].every((appId) => appId in prev)) {
						return prev;
					}
					const next = { ...prev };
					for (const appId of appIdSet) {
						next[appId] ??= applicationManagementApi
							.getApplicationInfo(
								{
									appId,
								},
								{ signal: abortSignal }
							)
							.then((appInfo) => appInfo.name);
					}
					return next;
				});
				return clientInfoList;
			})
			.finally(() => {
				setProcessingIdList((prev) => prev.filter((id) => id !== processingId));
			});
	}, [abortSignal]);

	const [fetchClientInfoListPromise, setFetchClientInfoListPromise] = useState<
		Promise<ClientInfo[]> | undefined
	>(undefined);

	const onClickReload = useCallback(() => {
		setFetchClientInfoListPromise(fetchClientInfoList());
	}, [fetchClientInfoList]);
	const onClickDelete = useCallback(
		async (clientId: string) => {
			if (window.confirm("本当に削除しますか？")) {
				const processingId = Math.random();
				setProcessingIdList((prev) => [...prev, processingId]);
				clientManagementApi
					.deleteClientInfo({
						clientId,
					})
					.then(() => {
						onClickReload();
					})
					.finally(() => {
						setProcessingIdList((prev) =>
							prev.filter((id) => id !== processingId)
						);
					});
			}
		},
		[onClickReload]
	);
	const onCloseAddNewClientDialog = useCallback(
		(isSuccess?: boolean) => {
			setIsAddNewClientDialogOpen(false);
			if (isSuccess) {
				onClickReload();
			}
		},
		[onClickReload]
	);

	useEffect(() => {
		if (CLIENT_REGISTER_PARAMS == null) {
			setFetchClientInfoListPromise(fetchClientInfoList());
		}
	}, [fetchClientInfoList]);

	return (
		<>
			<h2>クライアント管理</h2>
			<Button onClick={onClickBack} variant="text">
				戻る
			</Button>
			<Divider />
			<Typography variant="body1">
				あなたが使用しているクライアント一覧を表示しています。なお、このデモアプリは一覧に含まれません。
				<br />
				使用しなくなったクライアントは適宜削除してください。
			</Typography>
			<Stack direction="row" spacing={2} sx={{ mt: 2 }}>
				<Button
					onClick={onClickReload}
					disabled={isProcessing}
					loading={isProcessing}
					variant="outlined"
					color="info"
				>
					再読み込み
				</Button>
				<Button
					onClick={() => setIsAddNewClientDialogOpen(true)}
					disabled={isProcessing}
					loading={isProcessing}
					variant="contained"
					startIcon={<MdAdd />}
				>
					追加
				</Button>
			</Stack>
			<ErrorBoundary fallback={<Typography>エラーが発生しました。</Typography>}>
				<Suspense fallback={<LinearProgress />}>
					{fetchClientInfoListPromise && (
						<ClientInfoList
							promise={fetchClientInfoListPromise}
							appNamePromiseMap={appNamePromiseMap}
							onClickDelete={onClickDelete}
						/>
					)}
				</Suspense>
			</ErrorBoundary>
			<AddNewClientDialog
				open={isAddNewClientDialogOpen}
				onClose={onCloseAddNewClientDialog}
			/>
		</>
	);
});

type ClientInfoListProps = {
	promise: Promise<ClientInfo[]>;
	appNamePromiseMap: Record<string, Promise<string>>;
	onClickDelete: (clientId: string) => void;
};
const ClientInfoList = memo<ClientInfoListProps>(function ClientInfoList({
	promise,
	appNamePromiseMap,
	onClickDelete,
}) {
	const clientInfoList = use(promise);

	if (clientInfoList == null || clientInfoList.length === 0) {
		return <Typography>クライアントが登録されていません。</Typography>;
	}

	return (
		<TableContainer component={Paper} sx={{ mt: 2 }}>
			<Table size="small">
				<TableHead>
					<TableRow>
						<TableCell>クライアント名</TableCell>
						<TableCell>アプリ名</TableCell>
						<TableCell>作成日時</TableCell>
						<TableCell>操作</TableCell>
					</TableRow>
				</TableHead>
				<TableBody>
					{clientInfoList.map((clientInfo) => (
						<TableRow key={clientInfo.clientId}>
							<TableCell>{clientInfo.name}</TableCell>
							<TableCell>
								<ErrorBoundary
									fallback={<Typography>エラーが発生しました。</Typography>}
								>
									<Suspense fallback={<CircularProgress size="1em" />}>
										{use(appNamePromiseMap[clientInfo.appId])}
									</Suspense>
								</ErrorBoundary>
							</TableCell>
							<TableCell>{clientInfo.createdAt.toLocaleString()}</TableCell>
							<TableCell>
								<IconButton onClick={() => onClickDelete(clientInfo.clientId)}>
									<MdDelete />
								</IconButton>
							</TableCell>
						</TableRow>
					))}
				</TableBody>
			</Table>
		</TableContainer>
	);
});
