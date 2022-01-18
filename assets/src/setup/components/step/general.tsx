import * as React from 'react';
import {useState, useEffect} from 'react';
import {
	Flex, FormControl, FormLabel, Select, Input, NumberInput,
	NumberInputField,
	NumberInputStepper,
	NumberIncrementStepper,
	NumberDecrementStepper
} from "@chakra-ui/react";
import SikshyaAPIFetch from "../../global/api";
import Paragraph_Skeleton from "../../skeleton/paragraph";
import {GeneralSettings} from "../../types/general-settings";

const General = (props: any) => {
	const [apiResponse, setApiResponse] = useState<GeneralSettings>();
	const apiCall = () => {
		new Promise<void>((resolve, reject) => {
			SikshyaAPIFetch({
				path: '/sikshya/v1/settings',
				method: 'GET',
			}).then((response) => {
				setApiResponse(response);
				props.updateGeneralSetting(response);
			});
		});
	}

	useEffect(() => {

		let size = !apiResponse ? 0 : Object.keys(apiResponse).length;
		if (size == 0) {
			apiCall();
		}
	}, [apiResponse]);

	const update = (event: any) => {
		const value = event.target.value;
		const id = event.target.id;
		props.updateGeneralSettingItem(id, value);
	}

	let size = !apiResponse ? 0 : Object.keys(apiResponse).length;
	if (size < 1) {

		return (<Paragraph_Skeleton/>);

	}

	// @ts-ignore
	return (
		<Flex flexDir="column" width="100%" gap={5}>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='currency'>Currency</FormLabel>
					<Select id='currency' placeholder='Select currency' w="md" onChange={update}>
						{Object.keys(sikshyaSetup.currencies).map((currency_key: string, index: number) => (
							<option selected={currency_key === apiResponse.currency}
									value={currency_key}>{sikshyaSetup.currencies[currency_key]}</option>
						))}
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='currency_symbol_type'>Currency Symbol Type</FormLabel>
					<Select id='currency_symbol_type' placeholder='Currency Symbol Type' w="md" onChange={update}>
						{Object.keys(sikshyaSetup.currency_symbol_type).map((symbol_type_key: string, index: number) => (
							<option selected={symbol_type_key === apiResponse.currency_symbol_type}
									value={symbol_type_key}>{sikshyaSetup.currency_symbol_type[symbol_type_key]}</option>
						))}

					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='currency_position'>Currency Position</FormLabel>
					<Select id='currency_position' placeholder='Currency Position' w="md" onChange={update}>
						{Object.keys(sikshyaSetup.currency_positions).map((position_key: string, index: number) => (
							<option selected={position_key === apiResponse.currency_position}
									value={position_key}>{sikshyaSetup.currency_positions[position_key]}</option>
						))}

					</Select>
				</Flex>
			</FormControl>

			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='thousand_separator'>Thousand Separator</FormLabel>
					<Input id='thousand_separator' placeholder='Thousand Separator' w="md"
						   defaultValue={apiResponse.thousand_separator} onChange={update}/>

				</Flex>
			</FormControl>

			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='number_of_decimals'>Number Of Decimals</FormLabel>

					<NumberInput id='number_of_decimals' defaultValue={apiResponse.number_of_decimals} max={10}
								 clampValueOnBlur={false} w="md">
						<NumberInputField onChange={update}/>
						<NumberInputStepper>
							<NumberIncrementStepper/>
							<NumberDecrementStepper/>
						</NumberInputStepper>
					</NumberInput>
				</Flex>
			</FormControl>

			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='decimal_separator'>Decimal Separator</FormLabel>

					<Input id='decimal_separator' placeholder='Decimal Separator' w="md"
						   defaultValue={apiResponse.decimal_separator} onChange={update}/>
				</Flex>
			</FormControl>
		</Flex>
	);
};
export default General
