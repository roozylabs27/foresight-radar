import React, { useState } from "react";
import { Layout, Flex, Button, Dropdown, Space } from "antd";
import {
    UserOutlined,
    LogoutOutlined,
    MenuFoldOutlined,
    MenuUnfoldOutlined,
    DownOutlined,
} from "@ant-design/icons";
import { Link } from "@inertiajs/react";

export default function Navbar({
    collapsed,
    setCollapsed,
    colorBgContainer,
    user,
}) {
    const { Header } = Layout;
    const [items, setItems] = useState([
        // {
        //     key: "0",
        //     label: (
        //         <Link
        //             href={route("profile.edit")}
        //         >
        //             <Flex gap="middle" vertical={false}>
        //                 <UserOutlined />
        //                 Profile
        //             </Flex>
        //         </Link>
        //     ),
        // },
        {
            key: "1",
            label: (
                <Link
                    href={route("logout")}
                    method="post"
                    as="button"
                    type="button"
                >
                    <Flex gap="middle" vertical={false}>
                        <LogoutOutlined />
                        Logout
                    </Flex>
                </Link>
            ),
        },
    ]);

    return (
        <>
            <Header
                style={{
                    padding: 0,
                    background: colorBgContainer,
                }}
            >
                <Flex
                    justify="space-between"
                    align="flex-start"
                >
                    <Button
                        style={{
                            marginLeft: "0.5rem",
                            width: "64px",
                            height: "64px",
                            fontSize: "16px",
                        }}
                        type="text"
                        icon={
                            collapsed ? (
                                <MenuUnfoldOutlined />
                            ) : (
                                <MenuFoldOutlined />
                            )
                        }
                        onClick={() => setCollapsed(!collapsed)}
                    />
                    <Dropdown
                        style={{
                            marginRight: '1.25rem'
                         }}
                        placement="topLeft"
                        menu={{
                            items,
                        }}
                        trigger={["hover"]}
                    >
                        <a onClick={(e) => e.preventDefault()}>
                            <Space size="small">
                                Hi, {user ? user.name : "Member"}
                                <DownOutlined />
                            </Space>
                        </a>
                    </Dropdown>
                </Flex>
            </Header>
        </>
    );
}
