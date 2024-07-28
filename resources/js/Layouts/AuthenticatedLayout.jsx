import React, { useState } from "react";
import {
    UploadOutlined,
    UserOutlined,
    VideoCameraOutlined,
    MenuFoldOutlined,
    MenuUnfoldOutlined,
    DownOutlined,
} from "@ant-design/icons";
import { Layout, Menu, theme, Flex, Button, Dropdown, Space } from "antd";
const { Header, Content, Footer, Sider } = Layout;

const items = [
    UserOutlined,
    VideoCameraOutlined,
    UploadOutlined,
    UserOutlined,
].map((icon, index) => ({
    key: String(index + 1),
    icon: React.createElement(icon),
    label: `nav ${index + 1}`,
}));

export default function Authenticated({ user, children }) {
    const {
        token: { colorBgContainer },
    } = theme.useToken();

    const [collapsed, setCollapsed] = useState(false);

    return (
        <Layout
            style={{
                minHeight: "100vh",
            }}
        >
            <Sider
                breakpoint="lg"
                collapsedWidth="0"
                theme="light"
                onBreakpoint={(broken) => {
                    console.log(broken);
                }}
                onCollapse={(collapsed, type) => {
                    console.log(collapsed, type);
                }}
                collapsed={collapsed}
                collapsible
                trigger={null}
            >
                <div
                    style={{
                        height: "32px",
                        margin: "16px",
                        background: "rgba(255,255,255,.2)",
                        borderRadius: "6px",
                    }}
                    className="demo-logo-vertical"
                />
                <Menu
                    theme="light"
                    mode="inline"
                    defaultSelectedKeys={["4"]}
                    items={items}
                />
            </Sider>
            <Layout>
                <Header
                    style={{
                        padding: 0,
                        background: colorBgContainer,
                    }}
                >
                    <Flex
                        style={{
                            width: "100%",
                        }}
                        justify="space-between"
                        align="flex-start"
                    >
                        <Button
                            type="text"
                            icon={
                                collapsed ? (
                                    <MenuUnfoldOutlined />
                                ) : (
                                    <MenuFoldOutlined />
                                )
                            }
                            onClick={() => setCollapsed(!collapsed)}
                            style={{
                                fontSize: "16px",
                                width: 64,
                                height: 64,
                            }}
                        />
                        <Dropdown
                            placement="topLeft"
                            menu={{
                                items,
                                onClick: ({ key }) => {
                                    handleDropdownItemClick(key, record);
                                },
                            }}
                            trigger={["click"]}
                        >
                            <a
                                style={{
                                    marginRight: "20px",
                                }}
                                onClick={(e) => e.preventDefault()}
                            >
                                <Space>
                                    Hi, {user?.name}
                                    <DownOutlined />
                                </Space>
                            </a>
                        </Dropdown>
                    </Flex>
                </Header>
                {children}
                <Footer
                    style={{
                        textAlign: "center",
                    }}
                >
                    Ant Design ©{new Date().getFullYear()} Created by Ant UED
                </Footer>
            </Layout>
        </Layout>
    );
}
