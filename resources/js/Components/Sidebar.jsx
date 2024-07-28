import React, { useState } from "react";
import { Layout, Menu } from "antd";
import {
    UploadOutlined,
    UserOutlined,
    VideoCameraOutlined,
} from "@ant-design/icons";

export default function Sidebar({ collapsed }) {
    const { Sider } = Layout;
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
    const [theme, setTheme] = useState("light");

    return (
        <>
            <Sider
                breakpoint="lg"
                collapsedWidth="0"
                theme={theme}
                collapsed={collapsed}
                collapsible
                trigger={null}
            >
                <div className="demo-logo-vertical h-[32px] m-[16px] bg-slate-500 rounded" />
                <Menu
                    theme={theme}
                    mode="inline"
                    defaultSelectedKeys={["4"]}
                    items={items}
                />
            </Sider>
        </>
    );
}
