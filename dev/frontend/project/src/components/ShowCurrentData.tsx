import { memo } from "react";
import WebRTCContext from "./WebRTCContext";
import { ROLE } from "../webrtc/ConnectionManager";
import { Button, Divider } from "@mui/material";
import BidsDataProvider from "../bids/BidsDataProvider";

type ShowCurrentDataProps = {
	onClickBack: () => void;
};
export default memo<ShowCurrentDataProps>(function ShowCurrentData({
	onClickBack,
}) {
	return (
		<WebRTCContext role={ROLE.SUBSCRIBER}>
			<Button onClick={onClickBack} variant="text">
				戻る
			</Button>
			<Divider />
			<BidsDataProvider>
				<BidsDataProvider />
			</BidsDataProvider>
		</WebRTCContext>
	);
});
