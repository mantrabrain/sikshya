import * as React from 'react';
import {Flex, FormControl, FormLabel, Select} from "@chakra-ui/react";

const Pages = (props: any) => {
	const update = (event: any) => {
		const value = event.target.value;
		const id = event.target.id;
		props.updateSettingItem(id, value);
	}
	const RenderPages = (selected_page_id: string) => {
		return (

			Object.keys(sikshyaSetup.all_pages).map((page_id: string, index: number) => (
				<option selected={page_id === selected_page_id}
						value={page_id}>{sikshyaSetup.all_pages[page_id]}</option>
			))

		)
	}
	return (
		<Flex flexDir="column" width="100%" gap={5}>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='account_page'>Account Page</FormLabel>
					<Select id='account_page' placeholder='Select Account Page' w="md" onChange={update}>
						{RenderPages(sikshyaSetup.account_page)}
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='registration_page'>Registration Page</FormLabel>
					<Select id='registration_page' placeholder='Select Registration Page' w="md" onChange={update}>
						{RenderPages(sikshyaSetup.registration_page)}
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='login_page'>Login Page</FormLabel>
					<Select id='login_page' placeholder='Select Login Page' w="md" onChange={update}>
						{RenderPages(sikshyaSetup.login_page)}
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='cart_page'>Cart Page</FormLabel>
					<Select id='cart_page' placeholder='Select Cart Page' w="md" onChange={update}>
						{RenderPages(sikshyaSetup.cart_page)}
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='checkout_page'>Checkout Page</FormLabel>
					<Select id='checkout_page' placeholder='Select Checkout Page' w="md" onChange={update}>
						{RenderPages(sikshyaSetup.checkout_page)}
					</Select>
				</Flex>
			</FormControl>
		</Flex>
	);
};
export default Pages
