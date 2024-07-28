import React, { useState } from "react";
import { Layout, theme } from "antd";
import Sidebar from "@/Components/Sidebar";
import Navbar from "@/Components/Navbar";

export default function Authenticated({ user, children }) {
    const {
        token: { colorBgContainer },
    } = theme.useToken();

    const [collapsed, setCollapsed] = useState(false);

    return (
        <Layout className="min-h-screen"
        >
            <Sidebar collapsed={collapsed} />
            <Layout>
                <Navbar
                    collapsed={collapsed}
                    setCollapsed={setCollapsed}
                    colorBgContainer={colorBgContainer}
                    user={user}
                />
                {children}
            </Layout>
        </Layout>
    );
}
