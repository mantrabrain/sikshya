import * as React from 'react';
import StepBox from "./../parts/stepbox";

type CourseProps = {
	index: number
}

const Course = (props: CourseProps) => {

	return (
		<StepBox>
			<div>
				<h1>This is Course Step{props.index}</h1>

			</div>
		</StepBox>
	);
};
export default Course
