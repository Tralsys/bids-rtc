import { Box, CircularProgress, Modal, styled } from "@mui/material";
import { memo } from "react";

type ModalProgressProps = {
	open?: boolean;
};
export default memo<ModalProgressProps>(function Page({ open = true }) {
	return (
		<Modal open={open}>
			<StyledBox>
				<CircularProgress size="6rem" />
			</StyledBox>
		</Modal>
	);
});

const StyledBox = styled(Box)({
	height: "100%",
	width: "100%",
	display: "flex",
	justifyContent: "center",
	alignItems: "center",
});
