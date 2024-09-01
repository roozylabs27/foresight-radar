import ApplicationLogo from "@/Components/ApplicationLogo";
import { Link } from "@inertiajs/react";

import { Layout } from "antd";


export default function GuestLayout({ children }) {
    const { Header, Content, Footer, Sider } = Layout;

    return (
        <Layout className="layout">
            <Content
                style={{
                    padding: "50px",
                    minHeight: "100vh",
                    display: "flex",
                    justifyContent: "center",
                    alignItems: "center",
                }}
            >
                <div style={{ width: "100%", maxWidth: "400px" }}>
                    {children}
                </div>
            </Content>
        </Layout>
    );
}
