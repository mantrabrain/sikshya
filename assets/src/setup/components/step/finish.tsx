import * as React from 'react';
import StepBox from "./../parts/stepbox";

type FinishProps = {
	index: number
}

const Finish = (props: FinishProps) => {

	return (
		<StepBox>
			<div>
				<h1>Finish Step {props.index}</h1>

			</div>
		</StepBox>
	);
};
export default Finish
