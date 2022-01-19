import * as React from 'react';
import {useState, useEffect} from 'react';
import {Flex, FormControl, FormLabel, Select} from "@chakra-ui/react";
import {PagesSettings} from "../../types/pages-settings";
import SetupAPI from "../../api/setup-api";
import Paragraph_Skeleton from "../../skeleton/paragraph";

const Pages = (props: any) => {

	const RenderPages = (selected_page_id: string) => {

		return (

			Object.keys(sikshyaSetup.all_pages).map((page_id: string, index: number) => (
				<option selected={parseInt(page_id) === parseInt(selected_page_id) && parseInt(selected_page_id) != 0}
						value={page_id}>{sikshyaSetup.all_pages[page_id]}</option>
			))

		)
	}

	const [pagesAPIResponse, setPagesAPIResponse] = useState<PagesSettings>();
	const {initPagesSetting} = new SetupAPI;

	const callbackCall = (response: any) => {
		setPagesAPIResponse(response);
		props.updateSettings(response);
	}
	useEffect(() => {

		let size = !pagesAPIResponse ? 0 : Object.keys(pagesAPIResponse).length;
		if (size == 0) {
			initPagesSetting(callbackCall);
		}
	}, [pagesAPIResponse]);

	const update = (event: any) => {
		const value = event.target.value;
		const id = event.target.id;
		if (pagesAPIResponse.hasOwnProperty(id)) {
			// @ts-ignore
			pagesAPIResponse[id] = value;
		}
		props.updateSettings(pagesAPIResponse);
	}
	let size = !pagesAPIResponse ? 0 : Object.keys(pagesAPIResponse).length;
	if (size < 1) {

		return (<Paragraph_Skeleton/>);

	}
	return (
		<Flex flexDir="column" width="100%" gap={5}>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='account_page'>Account Page</FormLabel>
					<Select id='account_page' placeholder='Select Account Page' w="md" onChange={update}>
						{RenderPages(pagesAPIResponse.account_page)}
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='registration_page'>Registration Page</FormLabel>
					<Select id='registration_page' placeholder='Select Registration Page' w="md" onChange={update}>
						{RenderPages(pagesAPIResponse.registration_page)}
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='login_page'>Login Page</FormLabel>
					<Select id='login_page' placeholder='Select Login Page' w="md" onChange={update}>
						{RenderPages(pagesAPIResponse.login_page)}
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='cart_page'>Cart Page</FormLabel>
					<Select id='cart_page' placeholder='Select Cart Page' w="md" onChange={update}>
						{RenderPages(pagesAPIResponse.cart_page)}
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='checkout_page'>Checkout Page</FormLabel>
					<Select id='checkout_page' placeholder='Select Checkout Page' w="md" onChange={update}>
						{RenderPages(pagesAPIResponse.checkout_page)}
					</Select>
				</Flex>
			</FormControl>
		</Flex>
	);
};
export default Pages
