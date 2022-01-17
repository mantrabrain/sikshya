import * as React from 'react';

type CourseProps = {
	index: number
}

const Course = (props: CourseProps) => {

	return (
		<div>
			<div>
				<h1>This is Course Step{props.index}</h1>

			</div>
		</div>
	);
};
export default Course
